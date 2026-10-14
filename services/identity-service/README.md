# Identity Service

Servizio Yii2 autonomo che autentica gli account admin/player usati da
Connected Games Platform. L'app principale invia le credenziali a questo
servizio; le password non vengono validate né conservate dall'app principale.

## Database e migrazioni

Creare `connected_games_identity` in MySQL e, da questa directory, eseguire:

```powershell
composer install
php yii migrate
```

Le tabelle del servizio, inclusa la tabella di storico migrazioni, usano il
prefisso `tbl_`.

## Account

Per trasferire gli account dal database legacy `connected_games_db` senza
cambiare username o password:

```powershell
php yii identity/import-legacy-users
```

L'import copia username, ruolo e hash password e conserva l'ID legacy per
collegare in futuro i profili player. Non modifica il database sorgente.

Per creare o reimpostare account:

```powershell
php yii identity/create-user <username> admin
php yii identity/create-user <username> player
php yii identity/set-password <username>
php yii identity/revoke-all-tokens <username>
```

Le password sono richieste interattivamente e devono avere almeno 12 caratteri.
Non esistono endpoint di registrazione pubblica.

## Avvio in PhpStorm

Creare una configurazione **PHP Script**:

- File: `services/identity-service/yii`
- Working directory: `services/identity-service`
- Arguments: `serve 127.0.0.1:8001 --docroot=web --router=web/index.php`
- PHP: 8.2 o superiore, con `pdo_mysql`

Impostare le variabili d'ambiente:

```text
IDENTITY_DB_HOST=localhost;IDENTITY_DB_PORT=3306;IDENTITY_DB_NAME=connected_games_identity;IDENTITY_DB_USER=root;IDENTITY_DB_PASSWORD=;IDENTITY_COOKIE_KEY=<segreto-casuale-lungo>
```

## API usata dall'app principale

- `POST /api/v1/auth/login`: username e password; restituisce un bearer token
  di 8 ore e le informazioni minime del soggetto.
- `GET /api/v1/auth/me`: valida il token e restituisce subject, username e
  ruolo. Il monolite lo usa per verificare la sessione applicativa.
- `GET /api/v1/users/players`: restituisce gli account player abilitati; richiede
  un bearer token admin. Il monolite usa l'elenco per assegnarli alle partite.
- `POST /api/v1/auth/logout`: revoca il token corrente.

Nel database il token viene conservato soltanto come hash SHA-256. Login e
logout non sono disponibili via GET; le risposte con token hanno intestazioni
`Cache-Control: no-store`. In produzione l'URL del servizio deve usare HTTPS.
