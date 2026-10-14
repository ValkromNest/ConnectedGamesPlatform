# Connected Games Platform

Applicazione Yii2 monolitica con servizio autonomo per le partite di freccette. Il codice resta fuori dalle cartelle XAMPP: XAMPP fornisce Apache e MySQL, mentre i server PHP dell'applicazione e del servizio vengono avviati separatamente da PhpStorm.

## Requisiti

- Windows, XAMPP con MySQL e PHP 8.2 o successivo.
- Composer 2.
- PhpStorm con supporto PHP e un interprete PHP configurato. Selezionare lo stesso PHP usato per i comandi Composer e verificare che abbia attiva l'estensione `pdo_mysql`.
- Git, per clonare il repository.

Verifica PHP e PDO MySQL dal terminale configurato per il progetto:

```powershell
php --version
php -m | findstr /I "PDO pdo_mysql"
```

## 1. Clonare il progetto e installare le dipendenze

Aprire PowerShell nella cartella in cui si vuole collocare il progetto:

```powershell
git clone <URL-DEL-REPOSITORY> connected_games_platform
cd connected_games_platform
composer install
```

Installare anche le dipendenze del servizio Freccette, che ha il proprio `composer.json`:

```powershell
cd services/darts-game-service
composer install
cd ../..
```

Eseguire entrambi i `composer install` usando l'interprete PHP 8.2+ con `pdo_mysql` attivo. Il secondo comando Composer va eseguito nella cartella `services/darts-game-service`.

## 2. Configurare MySQL in XAMPP

Avviare MySQL dal pannello XAMPP. L'applicazione monolitica usa per impostazione predefinita:

- host `localhost`
- database `connected_games_db`
- utente `root`
- password vuota

Questi valori sono definiti in `config/db.php`; modificarli se la nuova macchina usa credenziali diverse.

Creare inoltre il database `connected_games_dartsgame` per il servizio. Da PowerShell, nella directory `services/darts-game-service`, applicare le migrazioni del servizio:

```powershell
php yii migrate --migrationPath=@darts/migrations
```

Se MySQL non usa i valori predefiniti, impostare prima nel terminale le variabili `DARTS_GAME_DB_HOST`, `DARTS_GAME_DB_PORT`, `DARTS_GAME_DB_NAME`, `DARTS_GAME_DB_USER` e `DARTS_GAME_DB_PASSWORD` con i valori indicati nella sezione PhpStorm qui sotto, poi eseguire il comando di migrazione nella stessa sessione PowerShell.

> **Database del monolite:** il repository non contiene un dump o una procedura completa per creare da zero tutte le tabelle di `connected_games_db`. Su una nuova installazione serve importare lo schema iniziale fornito separatamente; la migrazione sopra crea solo le tabelle del servizio Freccette.

## 3. Configurare PhpStorm

Configurare e avviare due server distinti. Il monolite sarà raggiungibile su `http://127.0.0.1:8080`; il servizio Freccette su `http://127.0.0.1:8003`.

### Monolite: PHP Built-in Web Server

In **Run | Edit Configurations...**, aggiungere una configurazione **PHP Built-in Web Server**:

| Campo | Valore |
|---|---|
| Name | `Connected Games Platform` |
| Host | `127.0.0.1` |
| Port | `8080` |
| Document root | `<percorso-progetto>/web` |
| Router script | `<percorso-progetto>/web/index.php` |
| PHP interpreter | l'interprete PHP 8.2+ con `pdo_mysql` attivo |

In **Environment variables**, impostare i token applicativi. Generare valori propri per ogni installazione e usare gli stessi identici valori nella configurazione del servizio:

```text
DARTS_GAME_SERVICE_URL=http://127.0.0.1:8003;DARTS_GAME_MATCHES_TOKEN=<token-partite>;DARTS_GAME_DEVICE_TOKEN=<token-dispositivi>
```

La connessione DB del monolite legge i valori da `config/db.php`, non da variabili d'ambiente.

### Servizio Freccette: PHP Script

In **Run | Edit Configurations...**, aggiungere una configurazione **PHP Script**:

| Campo | Valore |
|---|---|
| Name | `Darts Game Service` |
| File | `<percorso-progetto>/services/darts-game-service/yii` |
| Working directory | `<percorso-progetto>/services/darts-game-service` |
| PHP interpreter | lo stesso interprete PHP 8.2+ con `pdo_mysql` attivo |
| Arguments | `serve 127.0.0.1:8003 --docroot=web --router=web/index.php` |

In **Environment variables**, inserire i valori di MySQL e gli stessi token configurati nel monolite:

```text
DARTS_GAME_DB_HOST=localhost;DARTS_GAME_DB_PORT=3306;DARTS_GAME_DB_NAME=connected_games_dartsgame;DARTS_GAME_DB_USER=root;DARTS_GAME_DB_PASSWORD=;DARTS_GAME_MATCHES_TOKEN=<token-partite>;DARTS_GAME_DEVICE_TOKEN=<token-dispositivi>
```

`DARTS_GAME_DB_PASSWORD` è vuota nell'esempio per la configurazione XAMPP predefinita. Se PhpStorm non accetta una variabile vuota, ometterla quando la password MySQL è vuota. Se necessario si può aggiungere `DARTS_GAME_COOKIE_KEY=<chiave-casuale>`.

Avviare prima **Darts Game Service**, poi **Connected Games Platform**. Lasciare entrambe le configurazioni attive durante l'uso dell'applicazione.

## Note per una nuova macchina

- Non salvare token o password reali nei file del repository.
- Le cartelle `vendor` sono ignorate da Git: eseguire `composer install` nella root e nella cartella del servizio dopo ogni nuovo clone.
- Le porte `8080` e `8003` devono essere libere.
- MySQL deve essere avviato da XAMPP; PhpStorm esegue i due server PHP.
