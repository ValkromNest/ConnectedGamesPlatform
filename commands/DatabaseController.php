<?php

declare(strict_types=1);

namespace app\commands;

use RuntimeException;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Connection;

/** Crea i database MySQL mancanti e installa gli schemi solo se vuoti. */
final class DatabaseController extends Controller
{
    /**
     * Installa le tabelle dai file database/*.sql, senza eseguire migrazioni.
     * Uso dalla root: php yii database/install
     */
    public function actionInstall(): int
    {
        $root = Yii::getAlias('@app');
        $definitions = [
            'app principale' => ['config/db.php', 'connected_games_db.sql'],
            'identity-service' => ['services/identity-service/config/db.php', 'connected_games_identity.sql'],
            'darts-service' => ['services/darts-game-service/config/db.php', 'connected_games_dartsgame.sql'],
        ];
        $plans = [];
        $targets = [];

        try {
            // Tutti gli schemi sono controllati prima della prima scrittura.
            foreach ($definitions as $label => [$configuration, $filename]) {
                $sql = file_get_contents($root . '/database/' . $filename);
                if ($sql === false) {
                    throw new RuntimeException('File SQL non leggibile: ' . $filename);
                }
                // I file distribuiti contengono SQL semplice, senza routine o delimitatori personalizzati.
                $sql = preg_replace('/^\s*--[^\r\n]*/m', '', $sql);
                $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));
                if ($statements === []) {
                    throw new RuntimeException('File SQL vuoto: ' . $filename);
                }

                /** @var Connection $db */
                $db = Yii::createObject(require $root . '/' . $configuration);
                if ($db->driverName !== 'mysql') {
                    throw new RuntimeException($label . ': è richiesta una connessione MySQL/MariaDB.');
                }
                if (!preg_match('/(?:^|;)dbname=([^;]+)/i', substr($db->dsn, 6), $matches)
                    || !preg_match('/^[A-Za-z0-9_]{1,64}$/D', $matches[1])) {
                    throw new RuntimeException($label . ': configurare un dbname valido nella connessione MySQL.');
                }
                $database = $matches[1];
                // Connessione al server senza selezionare un database che potrebbe non esistere.
                $dsnParts = array_filter(
                    explode(';', substr($db->dsn, 6)),
                    static fn (string $part): bool => $part !== '' && stripos($part, 'dbname=') !== 0,
                );
                $db->dsn = 'mysql:' . implode(';', $dsnParts);
                $plans[] = ['db' => $db, 'database' => $database, 'label' => $label, 'statements' => $statements];
                $db->open();
                $server = $db->createCommand('SELECT @@hostname, @@port')->queryOne();
                $target = json_encode([$server, $database], JSON_THROW_ON_ERROR);
                if (isset($targets[$target])) {
                    throw new RuntimeException('Due applicazioni puntano allo stesso database: ' . $database);
                }
                $targets[$target] = true;

                $count = (int) $db->createCommand(
                    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema',
                    [':schema' => $database],
                )->queryScalar();
                if ($count !== 0) {
                    throw new RuntimeException($label . ': il database ' . $database . ' non è vuoto. Installazione annullata.');
                }
                $this->stdout($label . ': database ' . $database . " assente o vuoto, controllo superato.\n");
            }

            foreach ($plans as $plan) {
                $quotedDatabase = '`' . $plan['database'] . '`';
                $plan['db']->createCommand(
                    'CREATE DATABASE IF NOT EXISTS ' . $quotedDatabase
                    . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
                )->execute();
                $plan['db']->createCommand('USE ' . $quotedDatabase)->execute();
                // Ricontrolla prima delle tabelle, nel caso lo schema sia cambiato nel frattempo.
                $count = (int) $plan['db']->createCommand(
                    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema',
                    [':schema' => $plan['database']],
                )->queryScalar();
                if ($count !== 0) {
                    throw new RuntimeException($plan['label'] . ': database non più vuoto. Installazione interrotta.');
                }
                foreach ($plan['statements'] as $statement) {
                    $plan['db']->createCommand($statement)->execute();
                }
                $this->stdout($plan['label'] . ": schema installato.\n");
            }
            $this->stdout("Installazione completata. Creare i profili e associare gli utenti Keycloak come descritto nella guida.\n");
            return ExitCode::OK;
        } catch (Throwable $exception) {
            $this->stderr('Installazione non completata: ' . $exception->getMessage() . "\n");
            $this->stderr("Non vengono eliminate tabelle o dati. Il DDL MySQL non garantisce rollback: in caso di errore durante la creazione verificare gli schemi parziali.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        } finally {
            foreach ($plans as $plan) {
                $plan['db']->close();
            }
        }
    }
}
