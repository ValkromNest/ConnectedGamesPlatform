<?php

declare(strict_types=1);

namespace identity\commands;

use identity\models\IdentityUser;
use identity\models\IdentityAccessToken;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Connection;

final class IdentityController extends Controller
{
    public function actionCreateUser(string $username, string $role = 'admin'): int
    {
        $username = trim($username);
        if ($username === '' || strlen($username) > 100 || !in_array($role, ['admin', 'player'], true)) {
            $this->stderr("Uso: php yii identity/create-user <username> [admin|player]\n");
            return ExitCode::USAGE;
        }
        if (IdentityUser::find()->where(['username' => $username])->exists()) {
            $this->stderr("Username già esistente.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $password = $this->prompt('Password (almeno 12 caratteri): ', [
            'required' => true,
            'validator' => static fn (string $value): bool => strlen($value) >= 12,
            'error' => 'La password deve contenere almeno 12 caratteri.',
        ], true);
        $confirmation = $this->prompt('Ripeti la password: ', ['required' => true], true);
        if (!hash_equals($password, $confirmation)) {
            $this->stderr("Le password non corrispondono.\n");
            return ExitCode::DATAERR;
        }

        $user = new IdentityUser([
            'subject_id' => self::uuid(),
            'username' => $username,
            'password_hash' => Yii::$app->security->generatePasswordHash($password),
            'role' => $role,
            'is_enabled' => 1,
            'failed_login_attempts' => 0,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if (!$user->save()) {
            $this->stderr("Impossibile creare l'account: " . json_encode($user->errors, JSON_UNESCAPED_UNICODE) . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Account '{$username}' creato con ruolo '{$role}'.\n");
        return ExitCode::OK;
    }

    public function actionRevokeAllTokens(string $username): int
    {
        $user = IdentityUser::find()->where(['username' => trim($username)])->one();
        if ($user === null) {
            $this->stderr("Account non trovato.\n");
            return ExitCode::DATAERR;
        }
        IdentityAccessToken::updateAll(
            ['revoked_at' => gmdate('Y-m-d H:i:s')],
            ['identity_user_id' => $user->id, 'revoked_at' => null],
        );
        $this->stdout("Sessioni revocate per '{$username}'.\n");
        return ExitCode::OK;
    }

    public function actionSetPassword(string $username): int
    {
        $user = IdentityUser::find()->where(['username' => trim($username)])->one();
        if ($user === null) {
            $this->stderr("Account non trovato.\n");
            return ExitCode::DATAERR;
        }
        $password = $this->prompt('Nuova password (almeno 12 caratteri): ', [
            'required' => true,
            'validator' => static fn (string $value): bool => strlen($value) >= 12,
            'error' => 'La password deve contenere almeno 12 caratteri.',
        ], true);
        $confirmation = $this->prompt('Ripeti la password: ', ['required' => true], true);
        if (!hash_equals($password, $confirmation)) {
            $this->stderr("Le password non corrispondono.\n");
            return ExitCode::DATAERR;
        }
        $user->password_hash = Yii::$app->security->generatePasswordHash($password);
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        if (!$user->save(false, ['password_hash', 'failed_login_attempts', 'locked_until'])) {
            $this->stderr("Impossibile aggiornare la password.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        IdentityAccessToken::updateAll(
            ['revoked_at' => gmdate('Y-m-d H:i:s')],
            ['identity_user_id' => $user->id, 'revoked_at' => null],
        );
        $this->stdout("Password aggiornata e sessioni revocate per '{$username}'.\n");
        return ExitCode::OK;
    }

    public function actionImportLegacyUsers(): int
    {
        $source = new Connection([
            'dsn' => getenv('IDENTITY_LEGACY_DB_DSN') ?: 'mysql:host=localhost;port=3306;dbname=connected_games_db',
            'username' => getenv('IDENTITY_LEGACY_DB_USER') ?: 'root',
            'password' => getenv('IDENTITY_LEGACY_DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
        ]);
        $inserted = 0;
        $skipped = 0;
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $legacyUsers = $source->createCommand(
                'SELECT [[id]], [[username]], [[password_hash]], [[role]] FROM {{%tbl_user}} ORDER BY [[id]]',
            )->queryAll();
            foreach ($legacyUsers as $legacyUser) {
                $username = trim((string) $legacyUser['username']);
                $legacyId = (int) $legacyUser['id'];
                if ($username === '' || !in_array((string) $legacyUser['role'], ['admin', 'player'], true)) {
                    $skipped++;
                    continue;
                }
                $exists = IdentityUser::find()->where([
                    'or',
                    ['username' => $username],
                    ['legacy_user_id' => $legacyId],
                ])->exists();
                if ($exists) {
                    $skipped++;
                    continue;
                }
                $user = new IdentityUser([
                    'subject_id' => self::uuid(),
                    'legacy_user_id' => $legacyId,
                    'username' => $username,
                    'password_hash' => (string) $legacyUser['password_hash'],
                    'role' => (string) $legacyUser['role'],
                    'is_enabled' => 1,
                    'failed_login_attempts' => 0,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
                if (!$user->save()) {
                    throw new \RuntimeException('Cannot import legacy account ' . $legacyId . ': ' . json_encode($user->errors));
                }
                $inserted++;
            }
            $transaction->commit();
        } catch (\Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            $this->stderr('Import non completato: ' . $exception->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        } finally {
            $source->close();
        }
        $this->stdout("Account importati: {$inserted}; ignorati (già presenti o ruolo non supportato): {$skipped}.\n");
        return ExitCode::OK;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
