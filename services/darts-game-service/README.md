# Darts-game-service

Microservizio Yii2 che implementa le regole e conserva lo stato delle partite di **Freccette 501 con double-out** nella Connected Games Platform. È un'applicazione PHP separata, con dipendenze Composer, configurazione, entry point e database propri.

## Responsabilità

- Inizializzare una partita con identificativo, partecipanti e ordine dei turni forniti dall'app principale.
- Determinare il giocatore di turno e gestire le tre freccette per visita.
- Validare settore e moltiplicatore e calcolare il punteggio.
- Gestire bust, ripristino del punteggio di inizio visita e checkout su doppio.
- Registrare i tiri e restituire stato, storico e vincitore.
- Terminare amministrativamente una partita su richiesta dell'app principale.

Il servizio possiede le tabelle `darts_game_match`, `darts_game_player` e `darts_game_throw` nel database `connected_games_dartsgame`. Non legge i database degli altri componenti. I riferimenti a partita e giocatori sono identificativi opachi, senza foreign key verso altri database.

## Collaborazione con la piattaforma

```text
Simulatore → API dell'app principale → Darts-game-service
Console web → App principale → Darts-game-service
```

Il simulatore non contatta direttamente questo servizio: l'app principale autentica il dispositivo, individua la partita e inoltra il lancio. La console coordina configurazione generale, partecipanti e ciclo amministrativo; il servizio è responsabile delle regole e dell'esito sportivo. Le risposte HTTP riportano stato ed esito all'app principale.

Keycloak gestisce il login della console web. Questo servizio non verifica token utente Keycloak: le sue API usano credenziali interne dedicate.

## API implementate

Base URL locale: `http://127.0.0.1:8003`.

| Metodo e percorso | Operazione | Credenziale Bearer |
| --- | --- | --- |
| `PUT /api/v1/games/darts/matches/{matchId}/context` | Inizializzazione della partita | `DARTS_GAME_MATCHES_TOKEN` |
| `GET /api/v1/games/darts/matches/{matchId}/state` | Stato, turno, storico e vincitore | `DARTS_GAME_DEVICE_TOKEN` |
| `POST /api/v1/games/darts/matches/{matchId}/throws` | Registrazione del lancio | `DARTS_GAME_DEVICE_TOKEN` |
| `POST /api/v1/games/darts/matches/{matchId}/finish` | Terminazione amministrativa | `DARTS_GAME_MATCHES_TOKEN` |

`DARTS_GAME_DEVICE_TOKEN` è una credenziale interna condivisa con l'app principale: è distinta dal token individuale del dispositivo e dai token Keycloak.

### Inizializzazione

Il body JSON dell'operazione `context` contiene:

```json
{
  "contextVersion": 1,
  "gameCode": "darts",
  "configuration": {"startingScore": 501, "checkout": "double"},
  "players": [
    {"playerId": "subject-player-1", "displayName": "Giocatore 1", "turnOrder": 1},
    {"playerId": "subject-player-2", "displayName": "Giocatore 2", "turnOrder": 2}
  ]
}
```

Occorre l'header `Idempotency-Key` con UUID valido. Ripetere il medesimo contesto per la stessa partita restituisce lo stato corrente; un contesto differente produce un conflitto.

### Lancio

```json
{
  "deviceId": "42",
  "sector": 20,
  "multiplier": 3
}
```

Il servizio determina il giocatore di turno: il chiamante non sceglie un player nel body del lancio. Il settore può essere 0 (mancato), 1–20 o 25 (bull). Il moltiplicatore può essere 1–3; un mancato richiede 1 e il bull ammette soltanto 1 o 2.

Anche `throws` richiede `Idempotency-Key`: stessa chiave e stesso payload restituiscono la risposta salvata senza conteggiare nuovamente il tiro; stessa chiave con payload diverso produce 409. I risultati possibili sono `scored`, `missed`, `bust` e `checkout`.

`finish` non richiede body o chiave di idempotenza; se la partita è già terminata restituisce lo stato. Le risposte riuscite delle quattro operazioni hanno HTTP 200.

### Errori e correlazione

| Stato HTTP | Significato |
| --- | --- |
| 400 | JSON, campi del lancio o chiave di idempotenza non validi |
| 401 | Credenziale Bearer assente |
| 403 | Credenziale errata per l'operazione |
| 404 | Partita non trovata nelle operazioni su partita esistente |
| 409 | Conflitto di contesto, riutilizzo incompatibile della chiave o partita non in esecuzione |
| 422 | Contesto o combinazione di lancio non validi |
| 500 | Errore interno |
| 503 | Credenziale API richiesta non configurata sul server |

Gli errori del controller hanno forma:

```json
{
  "error": {
    "code": "game_conflict",
    "message": "Descrizione del problema",
    "correlationId": "11111111-1111-4111-8111-111111111111"
  }
}
```

L'header facoltativo `X-Correlation-ID` viene restituito se è un UUID valido; altrimenti il servizio ne genera uno. Il contratto completo, con schemi delle risposte e differenze tra gli endpoint, è in [OpenAPI](../../docs/openapi.yaml).

## Installazione del database

Per una nuova installazione eseguire Composer nella root e in entrambe le cartelle dei servizi, avviare MySQL e configurare le connessioni. Dalla root:

```powershell
php yii database/install
```

Il comando crea i database mancanti e usa gli script SQL in `database/`. Si interrompe se uno dei database contiene tabelle o viste. Non va eseguito sull'installazione già popolata.

Lo schema del servizio è in `database/connected_games_dartsgame.sql`. La procedura non esegue migrazioni né registra il loro storico: non applicare poi le vecchie migrazioni alle tabelle appena create.

Per la procedura completa vedere [INSTALLAZIONE COMPLETA](<../../INSTALLAZIONE COMPLETA.md>) e [database/README.md](../../database/README.md).

## Avvio in PhpStorm

Installare le dipendenze Composer nella directory del servizio e creare una configurazione **PHP Script**:

| Campo | Valore |
| --- | --- |
| File | `services/darts-game-service/yii` |
| Working directory | `services/darts-game-service` |
| Arguments | `serve 127.0.0.1:8003 --docroot=web --router=web/index.php` |
| Interprete | PHP 8.2+ con `pdo_mysql` |

Il router è necessario per inoltrare a Yii gli indirizzi API. Nell'ambiente della configurazione impostare:

```text
DARTS_GAME_DB_HOST=localhost
DARTS_GAME_DB_PORT=3306
DARTS_GAME_DB_NAME=connected_games_dartsgame
DARTS_GAME_DB_USER=root
DARTS_GAME_DB_PASSWORD=
DARTS_GAME_MATCHES_TOKEN=SOSTITUIRE_CON_TOKEN_PARTITE
DARTS_GAME_DEVICE_TOKEN=SOSTITUIRE_CON_TOKEN_GIOCO
DARTS_GAME_COOKIE_KEY=SOSTITUIRE_CON_UN_SEGRETO_CASUALE
```

Usare due token distinti e robusti, impostando gli stessi valori nell'app principale. Nell'app principale impostare anche `DARTS_GAME_SERVICE_URL=http://127.0.0.1:8003`. Non salvare credenziali reali nel repository. Per l'esecuzione ordinaria preferire un utente MySQL dedicato al solo database del servizio.

Se la password MySQL è vuota e PhpStorm non accetta una variabile vuota, ometterla. Riavviare i processi dopo aver cambiato le variabili. MySQL deve essere disponibile prima di usare le API. Il servizio può essere avviato separatamente dall'app principale; per provarne il flusso completo servono anche gli altri componenti.
