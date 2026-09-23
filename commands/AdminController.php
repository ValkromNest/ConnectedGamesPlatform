<?php

declare(strict_types=1);

namespace app\commands;

use app\models\User;
use RuntimeException;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/** Gestisce la creazione locale degli amministratori della piattaforma. */
class AdminController extends Controller
{
    /**
     * Crea un amministratore richiedendo la password in modo interattivo.
     *
     * La password non viene mai accettata come argomento del comando, quindi non
     * compare nella cronologia della shell o nell'elenco dei processi.
     */
    public function actionCreate(string $username): int
    {
        if (!$this->interactive) {
            $this->stderr("Il comando richiede una sessione interattiva.\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!preg_match('/\A[a-zA-Z0-9_.-]{3,100}\z/', $username)) {
            $this->stderr("Username non valido. Usa 3-100 caratteri: lettere, numeri, punto, trattino o underscore.\n");

            return ExitCode::DATAERR;
        }

        if (User::findByUsername($username) !== null) {
            $this->stderr("Username già esistente.\n");

            return ExitCode::DATAERR;
        }

        $password = $this->promptHidden('Password: ');
        $confirmation = $this->promptHidden('Conferma password: ');

        if ($password === '' || !hash_equals($password, $confirmation)) {
            $this->stderr("Le password non coincidono o sono vuote. Nessun utente è stato creato.\n");

            return ExitCode::DATAERR;
        }

        if (!$this->confirm("Creare l'amministratore \"{$username}\"?")) {
            $this->stdout("Operazione annullata.\n");

            return ExitCode::OK;
        }

        $administrator = new User([
            'username' => $username,
            'password_hash' => Yii::$app->security->generatePasswordHash($password),
            'auth_key' => Yii::$app->security->generateRandomString(),
            'role' => 'admin',
        ]);

        unset($password, $confirmation);

        if (!$administrator->save()) {
            $this->stderr("Impossibile creare l'amministratore.\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Amministratore creato con successo.\n");

        return ExitCode::OK;
    }

    private function promptHidden(string $prompt): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return $this->promptHiddenOnWindows($prompt);
        }

        $this->stdout($prompt);
        shell_exec('stty -echo');

        try {
            $password = fgets(STDIN);
        } finally {
            shell_exec('stty echo');
            $this->stdout(PHP_EOL);
        }

        return $password === false ? '' : rtrim($password, "\r\n");
    }

    private function promptHiddenOnWindows(string $prompt): string
    {
        $this->stdout($prompt);

        $script = '$secure = Read-Host -AsSecureString; '
            . '$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure); '
            . 'try { [Console]::Out.Write([Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)) } '
            . 'finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }';
        $process = proc_open(
            ['powershell.exe', '-NoProfile', '-Command', $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Impossibile avviare il prompt sicuro della password.');
        }

        $password = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if (proc_close($process) !== ExitCode::OK) {
            throw new RuntimeException('Impossibile leggere la password.');
        }

        $this->stdout(PHP_EOL);

        return rtrim($password, "\r\n");
    }
}
