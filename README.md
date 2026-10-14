# Connected Games Platform

Applicazione Yii2 principale per amministrare partite e dispositivi, affiancata da due servizi autonomi: autenticazione e gestione delle partite di Freccette. Il codice resta fuori dalle cartelle XAMPP: XAMPP fornisce MySQL (e Apache, se usato); PhpStorm avvia i server PHP alle porte `8080`, `8001` e `8003`.

## Requisiti

- Windows, XAMPP con MySQL e PHP 8.2 o successivo.
- Composer 2 e Git.
- PhpStorm con interprete PHP 8.2+ configurato e con `pdo_mysql` attivo.

Verifica PHP ed estensione PDO MySQL:

```powershell
php --version
php -m | findstr /I "PDO pdo_mysql"
```

## 1. Clonare il progetto e installare le dipendenze

```powershell
git clone <URL-DEL-REPOSITORY> connected_games_platform
cd connected_games_platform
composer install
```

Ogni servizio ha il proprio `composer.json` e `vendor`:

```powershell
cd services/identity-service
composer install
cd ../darts-game-service
composer install
cd ../..
```

Eseguire Composer con lo stesso interprete PHP configurato in PhpStorm.

## 2. Preparare MySQL in XAMPP

Avviare MySQL dal pannello XAMPP e creare questi database in phpMyAdmin:

- `connected_games_db` per l'applicazione principale;
- `connected_games_identity` per il servizio di autenticazione;
- `connected_games_dartsgame` per il servizio Freccette.

L'applicazione principale usa `localhost`, utente `root` e password vuota. Se i valori sono diversi, modificarli in `config/db.php`.

> Il repository non contiene un dump o una procedura completa per creare da zero tutte le tabelle di `connected_games_db`. Su una macchina nuova serve importare lo schema iniziale fornito separatamente.

Applicare la migrazione del servizio identità:

```powershell
cd services/identity-service
php yii migrate
```

Applicare la migrazione del servizio Freccette:

```powershell
cd ../darts-game-service
php yii migrate --migrationPath=@darts/migrations
cd ../..
```

Entrambe le migrazioni creano tabelle con il prefisso `tbl_` nel rispettivo database.

Dopo aver installato lo schema esistente di `connected_games_db`, applicare
anche la migrazione principale dalla root del progetto. Sostituisce il
riferimento numerico a `tbl_user` in `tbl_match_players` con il subject
Identity Service e uno snapshot del nome:

```powershell
php yii migrate
```

La migrazione si interrompe senza modificare la tabella se trova ancora righe
in `tbl_match_players`.

### Creare o trasferire l'account admin

Se `connected_games_db.tbl_user` contiene già account, importarli nel servizio di identità dalla cartella `services/identity-service`:

```powershell
php yii identity/import-legacy-users
```

Il comando copia username, ruolo e hash password; non modifica il database originale. Per creare un nuovo admin invece:

```powershell
php yii identity/create-user <username> admin
```

Il comando richiede la password due volte in modo interattivo e ne richiede almeno 12 caratteri. Account player si possono predisporre con ruolo `player`; l'accesso player ai resoconti verrà aggiunto in futuro.

## 3. Configurare PhpStorm

Creare tre configurazioni da **Run | Edit Configurations...**. Il login dell'app principale richiede che il servizio identità sia avviato.

### Applicazione principale: PHP Built-in Web Server

| Campo | Valore |
|---|---|
| Name | `Connected Games Platform` |
| Host | `127.0.0.1` |
| Port | `8080` |
| Document root | `<percorso-progetto>/web` |
| Router script | `<percorso-progetto>/web/index.php` |
| PHP interpreter | PHP 8.2+ con `pdo_mysql` |

In **Environment variables**:

```text
IDENTITY_SERVICE_URL=http://127.0.0.1:8001;DARTS_GAME_SERVICE_URL=http://127.0.0.1:8003;DARTS_GAME_MATCHES_TOKEN=<token-partite>;DARTS_GAME_DEVICE_TOKEN=<token-dispositivi>
```

Generare token propri e usare gli stessi valori per `DARTS_GAME_MATCHES_TOKEN` e `DARTS_GAME_DEVICE_TOKEN` anche nella configurazione del servizio Freccette. Il database principale è configurato in `config/db.php`, non tramite variabili d'ambiente.

### Servizio identità: PHP Script

| Campo | Valore |
|---|---|
| Name | `Identity Service` |
| File | `<percorso-progetto>/services/identity-service/yii` |
| Working directory | `<percorso-progetto>/services/identity-service` |
| PHP interpreter | PHP 8.2+ con `pdo_mysql` |
| Arguments | `serve 127.0.0.1:8001 --docroot=web --router=web/index.php` |

In **Environment variables**:

```text
IDENTITY_DB_HOST=localhost;IDENTITY_DB_PORT=3306;IDENTITY_DB_NAME=connected_games_identity;IDENTITY_DB_USER=root;IDENTITY_DB_PASSWORD=;IDENTITY_COOKIE_KEY=<segreto-casuale-lungo>
```

`IDENTITY_DB_PASSWORD` è vuota nell'esempio XAMPP; se PhpStorm non accetta un valore vuoto, omettere la variabile. Impostare per `IDENTITY_COOKIE_KEY` un valore casuale di almeno 32 caratteri, non salvarlo nel repository.

### Servizio Freccette: PHP Script

| Campo | Valore |
|---|---|
| Name | `Darts Game Service` |
| File | `<percorso-progetto>/services/darts-game-service/yii` |
| Working directory | `<percorso-progetto>/services/darts-game-service` |
| PHP interpreter | PHP 8.2+ con `pdo_mysql` |
| Arguments | `serve 127.0.0.1:8003 --docroot=web --router=web/index.php` |

In **Environment variables**:

```text
DARTS_GAME_DB_HOST=localhost;DARTS_GAME_DB_PORT=3306;DARTS_GAME_DB_NAME=connected_games_dartsgame;DARTS_GAME_DB_USER=root;DARTS_GAME_DB_PASSWORD=;DARTS_GAME_MATCHES_TOKEN=<token-partite>;DARTS_GAME_DEVICE_TOKEN=<token-dispositivi>
```

Avviare MySQL in XAMPP e poi i processi PhpStorm nell'ordine: **Identity Service**, **Darts Game Service**, **Connected Games Platform**. Lasciare attive tutte e tre le configurazioni durante l'uso.

## Note

- Il servizio identità conserva password hashate e salva nei database soltanto hash dei token di accesso. Il token ha durata massima di 8 ore; il logout lo revoca.
- Non salvare password, token o chiavi reali nei file del repository.
- Le cartelle `vendor` sono ignorate da Git: dopo un nuovo clone eseguire `composer install` nella root e in entrambe le cartelle `services`.
- MySQL deve essere avviato da XAMPP; le porte host `8080`, `8001` e `8003` devono essere libere.
