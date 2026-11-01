# Identity-service

Microservizio Yii2 dedicato ai profili della Connected Games Platform. Conserva identificativi applicativi, username, ruoli `admin`/`player` e collegamenti alle identità Keycloak. Espone API HTTP/JSON utilizzate dall'app principale.

## Responsabilità e autenticazione

Il login degli utenti è delegato a **Keycloak**. L'app web esegue Authorization Code con PKCE S256 e gestisce token, rinnovo e logout nella propria sessione PHP.

L'identity-service riceve l'access token nell'header `Authorization: Bearer ...` e lo verifica mediante introspezione autenticata Keycloak. Il componente interno `src/services/KeycloakTokenVerifier.php` controlla `active`, issuer, audience, subject e scadenza. Quindi cerca un profilo locale abilitato con `keycloak_subject` e `keycloak_issuer` corrispondenti.

Il `sub` restituito dalle API è il **subject_id applicativo locale**, non il subject Keycloak. L'associazione mantiene stabili i riferimenti ai giocatori nelle partite. I ruoli applicativi sono letti dal database locale: i ruoli realm Keycloak non vengono automaticamente usati per autorizzare la console.

Non sono più implementate le azioni `POST /api/v1/auth/login` e `POST /api/v1/auth/logout`. L'identity-service non emette i token utilizzati dal login web e non effettua la loro revoca in Keycloak.

## API implementate

Base URL locale: `http://127.0.0.1:8001`.

| Metodo e percorso | Accesso | Risposta |
| --- | --- | --- |
| `GET /api/v1/auth/me` | Token Keycloak valido e profilo locale abilitato | Profilo corrente |
| `GET /api/v1/users/players` | Stessi requisiti e ruolo locale `admin` | Elenco dei player abilitati, ordinato per username |

Esempio di risposta di `/api/v1/auth/me`:

```json
{
  "user": {
    "sub": "11111111-1111-4111-8111-111111111111",
    "username": "admin",
    "role": "admin"
  }
}
```

L'elenco player ha forma `{"players": [{"sub": "...", "username": "...", "role": "player"}]}`. L'array può essere vuoto.

Gli errori applicativi hanno forma `{"error": {"code": "unauthorized", "message": "..."}}`:

- **401**: header assente/non valido, token rifiutato, profilo non associato o disabilitato. Nell'implementazione attuale anche indisponibilità Keycloak o errori di configurazione durante introspezione vengono tradotti in 401.
- **403**: richiesta dell'elenco giocatori da un profilo senza ruolo admin.

Le risposte del controller hanno `Cache-Control: no-store` e `Pragma: no-cache`. Il contratto dettagliato è in [OpenAPI](../../docs/openapi.yaml). Gli endpoint alternativi di routing Yii non sono il contratto pubblico documentato.

## Installazione del database

Il servizio usa il database `connected_games_identity`, con tabelle `tbl_identity_user`, `tbl_identity_access_token` e `tbl_identity_login_attempt`. Le ultime due appartengono all'autenticazione precedente e non sono usate per verificare gli access token Keycloak.

Per una **nuova installazione**, eseguire prima Composer nella root e nei due servizi. Avviare MySQL e configurare le connessioni, quindi dalla root:

```powershell
php yii database/install
```

Il comando crea tutti e tre i database mancanti e installa gli schemi SQL, comprese le colonne Keycloak. Richiede database assenti o vuoti; non va eseguito sull'installazione già popolata. Non esegue migrazioni né registra uno storico: non applicare poi le vecchie migrazioni sullo schema già installato.

Per installazione completa e dettagli seguire [INSTALLAZIONE COMPLETA](<../../INSTALLAZIONE COMPLETA.md>) e [database/README.md](../../database/README.md).

## Avvio in PhpStorm

Creare una configurazione **PHP Script**:

| Campo | Valore |
| --- | --- |
| File | `services/identity-service/yii` |
| Working directory | `services/identity-service` |
| Arguments | `serve 127.0.0.1:8001 --docroot=web --router=web/index.php` |
| Interprete | PHP 8.2+ con `pdo_mysql` |

Installare le dipendenze con Composer nella cartella del servizio. Impostare nell'ambiente della configurazione Run:

```text
IDENTITY_DB_HOST=localhost
IDENTITY_DB_PORT=3306
IDENTITY_DB_NAME=connected_games_identity
IDENTITY_DB_USER=root
IDENTITY_DB_PASSWORD=
IDENTITY_COOKIE_KEY=SOSTITUIRE_CON_UN_SEGRETO_CASUALE
KEYCLOAK_ISSUER=http://localhost:8081/realms/connected-games-web
KEYCLOAK_CLIENT_ID=connected-games-web
KEYCLOAK_CLIENT_SECRET=SOSTITUIRE_CON_IL_SECRET_DEL_CLIENT
KEYCLOAK_AUDIENCE=connected-games-web
```

Usare i valori reali; i nomi realm e client sono distinti, anche quando coincidono. Se la password MySQL è vuota e PhpStorm non accetta una variabile vuota, ometterla. In uso ordinario preferire un account MySQL dedicato. Per `IDENTITY_COOKIE_KEY` usare almeno 32 caratteri casuali. Non versionare segreti reali.

Le variabili redirect/logout servono all'app web, non alla verifica dei token nell'identity-service. Riavviare il processo dopo aver modificato le variabili. Keycloak deve essere raggiungibile anche durante le richieste autenticate: la verifica avviene tramite introspezione. Fuori dall'ambiente locale usare HTTPS.

## Profili e associazione Keycloak

I comandi seguenti si eseguono dalla **root del repository**, usando il file `yii` di questo servizio:

```powershell
php .\services\identity-service\yii identity/create-user nomeutente admin
php .\services\identity-service\yii identity/create-user nomegiocatore player
```

Creare separatamente l'account in Keycloak, nel realm della piattaforma, e impostarne la password. Per associarlo al profilo locale:

```powershell
$keycloakUserId = Read-Host 'Incolla il vero ID utente Keycloak, non lo username'
php .\services\identity-service\yii identity/link-keycloak nomeutente $keycloakUserId http://localhost:8081/realms/connected-games-web
```

Il comando non associa automaticamente account per username. Il profilo deve esistere e l'issuer deve coincidere con quello configurato. L'amministratore del realm `master` non è automaticamente un account del realm della piattaforma.

Il comando `create-user` richiede ancora una password locale di almeno 12 caratteri: è un residuo del sistema precedente e non è la password utilizzata dal login web. Anche `identity/set-password` e `identity/revoke-all-tokens` restano disponibili, ma modificano soltanto password/token locali: non cambiano credenziali, token o sessioni Keycloak. Per queste operazioni usare Keycloak.

Il terminale non eredita le variabili delle configurazioni Run PhpStorm: impostare anche lì `IDENTITY_DB_*` quando si eseguono comandi console.
