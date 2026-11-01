# Installazione completa — Connected Games Platform

Questa guida riunisce integralmente il precedente README e la guida di installazione Keycloak, conservando requisiti, comandi, configurazioni e risoluzione dei problemi.

## Come usare questa guida

1. Preparare PHP, Composer e i tre database seguendo la **Parte 1**.
2. Configurare i processi PHP in PhpStorm.
3. Completare Keycloak, le variabili dei processi PHP e l'associazione dei profili seguendo la **Parte 2**.
4. Avviare MySQL, Keycloak (8081), identity-service (8001), darts-game-service (8003) e applicazione principale (8080).
5. Aprire la console su `http://127.0.0.1:8080`.

**Aggiornamento sul login:** la Parte 1 è il README precedente, conservato integralmente per non perdere informazioni. Le sue indicazioni sui token locali di otto ore, sulla validazione delle password e sul login precedente sono storiche: il login attuale è delegato a Keycloak, come descritto nella Parte 2. I comandi di creazione dei profili locali restano disponibili, ma le credenziali di accesso web si impostano in Keycloak. Le variabili Keycloak si aggiungono a quelle PHP della Parte 1.

**Migrazioni:** la migrazione dell'identity-service può già includere le colonne Keycloak. Se sono state create tramite `php yii migrate`, non applicare anche l'SQL della Parte 2. Se invece è stato usato l'SQL manuale, non rieseguire la stessa modifica tramite migrazione.

**Schema principale:** prima di una nuova installazione verificare lo schema SQL effettivamente disponibile in `database/` e le migrazioni presenti. Il precedente README segnala che lo schema iniziale va fornito separatamente: non assumere che il solo comando migrate ricostruisca un database principale vuoto.

---

# Parte 1 — README di installazione precedente

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

### Creare gli account

Gli account admin e player sono gestiti esclusivamente da Identity Service. Dalla cartella `services/identity-service` creare un account con:

```powershell
php yii identity/create-user <username> admin
php yii identity/create-user <username> player
```

La migrazione principale converte il roster ai subject ID e rimuove la vecchia tabella `tbl_user`. Prima di applicarla, assicurarsi che gli account necessari siano già presenti in Identity Service: dopo la rimozione, gli account si creano e si amministrano solo tramite quel servizio.


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


---

# Parte 2 — Installazione Keycloak

# Installazione e configurazione Keycloak su Windows

Questa guida descrive l'avvio locale senza Docker o macchina virtuale, con PHP eseguito da PhpStorm. La configurazione attuale usa:

| Componente | Indirizzo |
| --- | --- |
| Applicazione web | `http://127.0.0.1:8080` |
| Identity-service | `http://127.0.0.1:8001` |
| Keycloak | `http://localhost:8081` |
| Realm attuale | `connected-games-web` |
| Client ID | `connected-games-web` |

Realm e client sono due oggetti distinti, anche se qui hanno lo stesso nome. Keycloak gestisce le credenziali; l'identity-service conserva i profili e i ruoli applicativi. Darts-service e autenticazione dei dispositivi non cambiano.

## 1. Prerequisiti

- JDK compatibile con la versione di Keycloak installata, disponibile nel PATH o tramite `JAVA_HOME`.
- Distribuzione ZIP di Keycloak, scaricabile da https://www.keycloak.org/downloads.
- Applicazione web e identity-service già configurati, incluso il database MySQL `connected_games_identity`.

La cartella usata in questi esempi è `C:\PhpStormProjects\connected_games_platform\keycloak-26.8.1`. Se diversa, sostituirla nei comandi.

## 2. Primo avvio da PowerShell

Dalla root del progetto:

```powershell
$env:KC_BOOTSTRAP_ADMIN_USERNAME = 'admin'
$env:KC_BOOTSTRAP_ADMIN_PASSWORD = Read-Host 'Scegli la password iniziale di amministrazione Keycloak'

& '.\keycloak-26.8.1\bin\kc.bat' start-dev --http-port=8081
```

La password viene richiesta nel terminale: scegliere una password propria. Le variabili bootstrap creano l'amministratore al primo avvio; modificarle successivamente non cambia la password già memorizzata.

Se compare `JAVA_HOME is not set` ma Keycloak si avvia, Java è stato comunque trovato. Per impostare esplicitamente il JDK:

```powershell
$env:JAVA_HOME = 'C:\percorso\reale\del\jdk'
```

Quando compare `Listening on: http://localhost:8081`, il server è pronto. Il terminale resta occupato: è il comportamento normale. Lasciarlo aperto; `Ctrl+C` arresta il server. `start-dev` è destinato allo sviluppo locale.

Aprire http://localhost:8081/admin/ e accedere con l'amministratore appena creato.

## 3. Avvio da PhpStorm

In **Run → Edit Configurations → + → Shell Script**:

| Campo | Valore |
| --- | --- |
| Name | `Keycloak - 8081` |
| Script path | `C:\PhpStormProjects\connected_games_platform\keycloak-26.8.1\bin\kc.bat` |
| Script options | `start-dev --http-port=8081` |
| Interpreter path | `C:\Windows\System32\cmd.exe` |
| Interpreter options | `/c` |
| Working directory | `C:\PhpStormProjects\connected_games_platform\keycloak-26.8.1` |
| Execute in terminal | Disattivato |

Nel campo **Environment variables** inserire le variabili bootstrap solo se occorre il primo avvio; aggiungere `JAVA_HOME` se necessario. Non anteporre `KC_BOOTSTRAP_ADMIN_USERNAME=...` al comando PowerShell: quella sintassi genera un errore.

Premere Run per avviare Keycloak. Se è già avviato nel terminale, arrestarlo prima di avviare una seconda istanza.

## 4. Realm e client

### Installazione già configurata

Nel selettore dei realm scegliere **connected-games-web**. Non ricreare il realm né gli utenti se esistono già.

### Nuova installazione

Da **Manage realms → Create realm**, importare il file `config/keycloak/connected-games-realm.json`.

Attenzione: il file nel repository propone il nome realm **connected-games**, mentre l'installazione attuale usa **connected-games-web**. Per seguire questa guida, assegnare al realm il nome `connected-games-web` prima di completare la creazione. In alternativa mantenere `connected-games`, ma sostituire quel nome in tutti gli issuer e nel comando di associazione riportati sotto.

Dentro il realm aprire **Clients → connected-games-web**. Il client deve avere autenticazione abilitata, Standard Flow attivo e PKCE S256. L'importazione predispone queste impostazioni.

In **Settings** impostare e salvare:

| Campo | Valore |
| --- | --- |
| Valid redirect URIs | `http://127.0.0.1:8080/index.php?r=login%2Fcallback` |
| Valid post logout redirect URIs | `http://127.0.0.1:8080/index.php?r=login%2Findex` |

Rimuovere i vecchi URL sulla porta 8000 presenti nel file importato. Aprire **Credentials** e copiare il client secret per il passaggio successivo. Non inserirlo nei file versionati.

Il client deve includere `connected-games-web` nell'audience dell'access token. Il file importato contiene già il mapper `web-audience`. Se il client è stato creato manualmente, nel suo client scope dedicato aggiungere un mapper di tipo **Audience**, con **Included Client Audience** `connected-games-web` e **Add to access token** attivo.

## 5. Variabili delle applicazioni PHP

In PhpStorm aggiungere le seguenti variabili nelle configurazioni Run di **App Principale - 8080** e **Identity Service - 8001**:

```text
KEYCLOAK_ISSUER=http://localhost:8081/realms/connected-games-web
KEYCLOAK_CLIENT_ID=connected-games-web
KEYCLOAK_CLIENT_SECRET=INSERIRE_IL_SECRET_REALE_DEL_CLIENT
KEYCLOAK_AUDIENCE=connected-games-web
KEYCLOAK_REDIRECT_URI=http://127.0.0.1:8080/index.php?r=login%2Fcallback
KEYCLOAK_LOGOUT_URI=http://127.0.0.1:8080/index.php?r=login%2Findex
```

Ogni nome e valore va inserito separatamente nell'editor delle variabili. Sostituire il segnaposto del secret con quello copiato dalla console Keycloak. Conservare anche le variabili preesistenti del database e `IDENTITY_COOKIE_KEY`.

Riavviare entrambi i processi PHP: modificare la configurazione senza riavviarli non aggiorna l'ambiente del processo in esecuzione.

## 6. Aggiornamento del database identity

Prima di procedere, eseguire un backup. L'aggiornamento aggiunge due colonne a `tbl_identity_user` e un indice univoco; conserva utenti, password locali, ruoli e `subject_id`. Il database del darts-service non richiede modifiche.

Scegliere **uno solo** dei metodi seguenti.

### Metodo consigliato: migrazione Yii

Dalla root del progetto:

```powershell
php .\services\identity-service\yii migrate
```

La migrazione specifica è `m261031_120000_link_keycloak_profiles`; il comando può proporre anche altre migrazioni ancora pendenti, da controllare prima della conferma.

### Alternativa: SQL manuale

Eseguire sul database dell'identity-service, solo se le colonne non esistono già:

```sql
ALTER TABLE `tbl_identity_user`
    ADD COLUMN `keycloak_subject` VARCHAR(255) NULL,
    ADD COLUMN `keycloak_issuer` VARCHAR(255) NULL;

CREATE UNIQUE INDEX `uq_identity_keycloak`
    ON `tbl_identity_user` (`keycloak_issuer`, `keycloak_subject`);
```

Se l'SQL è già stato eseguito, non applicare nuovamente la stessa migrazione: l'esecuzione manuale non ne registra l'applicazione nella cronologia Yii.

## 7. Creazione dell'utente Keycloak

Nel realm **connected-games-web**:

1. Aprire **Users → Add user** e creare l'account della piattaforma.
2. In **Credentials → Set password**, impostare la password; disattivare **Temporary** per una password definitiva.
3. Completare gli eventuali campi o azioni richiesti dal realm.
4. Copiare l'**ID** dell'utente dalla pagina di dettaglio: è un identificativo simile a un UUID, non lo username.

L'amministratore del realm `master` serve ad amministrare Keycloak. Non è automaticamente un utente del realm della piattaforma.

## 8. Associazione al profilo locale

Nel database deve esistere un profilo locale abilitato con ruolo `admin`. I ruoli applicativi sono letti dal profilo locale: assegnare il solo ruolo Keycloak non rende amministratore della console.

Dalla root, incollare il vero ID Keycloak fra gli apici quando richiesto:

```powershell
$keycloakUserId = Read-Host 'Incolla ID utente da Keycloak (non username)'
php .\services\identity-service\yii identity/link-keycloak admin $keycloakUserId http://localhost:8081/realms/connected-games-web
```

`admin` è lo username del profilo locale. Sostituirlo se il profilo ha un altro nome. Il comando aggiorna l'associazione preservando il `subject_id` usato dalla piattaforma.

Non scrivere letteralmente `ID_UTENTE_KEYCLOAK`: il comando attuale accetta una stringa e salverebbe quel testo nel database. Non usare lo username al posto dell'ID.

Se il profilo locale non esiste, è disponibile:

```powershell
php .\services\identity-service\yii identity/create-user admin admin
```

Questo comando richiede ancora una password locale, che non verrà usata per il login web Keycloak. Dopo la creazione eseguire l'associazione.

### Connessione al database dal terminale

Il terminale non eredita le variabili definite nelle configurazioni Run di PhpStorm. Se necessario, impostare prima dei comandi Yii:

```powershell
$env:IDENTITY_DB_HOST = 'localhost'
$env:IDENTITY_DB_PORT = '3306'
$env:IDENTITY_DB_NAME = 'connected_games_identity'
$env:IDENTITY_DB_USER = 'root'
$env:IDENTITY_DB_PASSWORD = Read-Host 'Password database identity'
```

Usare i valori reali della configurazione locale.

## 9. Verifica del login

1. Avviare Keycloak, identity-service e applicazione web.
2. Aprire **http://127.0.0.1:8080** e premere **Accedi**.
3. Inserire le credenziali dell'utente nel realm `connected-games-web`.
4. Verificare il ritorno alla dashboard amministrativa.
5. Provare il logout e un nuovo accesso.

Per la console usare sempre `127.0.0.1:8080`, coerentemente con i redirect: alternarlo con `localhost:8080` può perdere la sessione necessaria alla verifica del login.

Verificare anche il rifiuto di un profilo player, disabilitato o non associato. Il vecchio login con password locale non è più disponibile.

Agli avvii successivi non serve importare il realm o ricreare gli utenti: mantenere i dati della distribuzione Keycloak. Occorre solo avviare i tre processi. Keycloak deve rimanere disponibile anche durante le richieste autenticate, perché l'identity-service verifica i token tramite introspezione.

## 10. Problemi comuni

| Errore | Controllo |
| --- | --- |
| `KC_BOOTSTRAP_ADMIN_USERNAME=admin` non riconosciuto | In PowerShell usare `$env:NOME = 'valore'`, oppure l'editor Environment variables di PhpStorm. |
| Il terminale resta fermo dopo `Listening on` | Il server è pronto e rimane in ascolto. Aprire il browser. |
| `Invalid username or password` nella console amministrativa | Usare la password del primo avvio riuscito. Cambiare le variabili bootstrap non modifica l'account esistente. |
| HTTP 404 su `/realms/connected-games/...` | Controllare il nome reale del realm. Qui è `connected-games-web`; correggere l'issuer in entrambi i processi e nell'associazione. |
| `Invalid parameter: redirect_uri` | Far coincidere esattamente redirect del client e `KEYCLOAK_REDIRECT_URI`, inclusi host, porta e `%2F`. |
| `Unknown command: identity/link-keycloak` | Dalla root usare `php .\services\identity-service\yii ...`, non `php yii ...`. |
| Accesso non riuscito dopo il login Keycloak | Controllare `keycloak_subject`, `keycloak_issuer`, ruolo `admin`, `is_enabled = 1`, secret, audience e riavvio dei processi PHP. |
| Colonne duplicate durante la migrazione | Verificare se è già stato eseguito l'SQL manuale. Non applicare due volte la stessa modifica. |
| Connessione DB negata dal terminale | Impostare le variabili `IDENTITY_DB_*` anche nella sessione PowerShell. |
| Porta occupata | L'app web usa 8080 e Keycloak 8081; non avviare due istanze sulla stessa porta. |

Per controllare i realm senza autenticarsi, aprire:

http://localhost:8081/realms/connected-games-web/.well-known/openid-configuration

Deve restituire un documento JSON. Per diagnosticare errori applicativi consultare `runtime/logs/app.log` e `services/identity-service/runtime/logs/app.log`, evitando di condividere token, cookie e segreti presenti nei log di sviluppo.

## Riferimenti

- Download: https://www.keycloak.org/downloads
- Configurazione server: https://www.keycloak.org/server/configuration
- OpenID Connect: https://www.keycloak.org/securing-apps/oidc-layers
- Configurazioni Shell Script PhpStorm: https://www.jetbrains.com/help/phpstorm/run-debug-configuration-shell-script.html
