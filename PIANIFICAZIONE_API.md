# Pianificazione API

Documento di progettazione per le API della Connected Games Platform. Descrive il flusso concordato e distingue le decisioni confermate dalle proposte ancora da approvare. Non definisce ancora lo schema OpenAPI definitivo.

## Flusso della partita

1. L'amministratore crea la partita dal gestionale.
2. L'amministratore assegna i player selezionandoli tra gli utenti con ruolo `player` già presenti nel database.
3. L'amministratore assegna alla partita un dispositivo registrato, per esempio `TavoloFreccette1`.
4. L'amministratore avvia la partita dal gestionale.
5. Il dispositivo si autentica e comunica al server gli eventi di gioco, in particolare ogni freccetta rilevata.
6. Il server valida stato, turno e tiro; calcola punteggio e bust, aggiorna la partita e restituisce il nuovo stato.
7. L'amministratore può terminare manualmente la partita dal gestionale. Un checkout valido la termina automaticamente.

Il gestionale resta responsabile della creazione della partita, dell'assegnazione dei player e del dispositivo, e dell'avvio. Un'eventuale automazione di queste operazioni è rinviata.

## Ruolo del simulatore e del dispositivo

- Il comando `php yii simulatore/play` eseguito nel progetto Yii2 richiama direttamente i servizi applicativi: non ha bisogno di passare dalle API.
- Un'app separata che simula il dispositivo, anche se è una semplice applicazione console, comunica invece con il server tramite API HTTP.
- Il dispositivo analogico e l'app che ne fa le veci sono client della stessa API e seguono lo stesso modello di autenticazione.
- Il server rimane l'unico componente che accede direttamente al database e applica le regole di gioco.

## Gestione dei dispositivi

Stato al 2026-10-08: il gestore base è stato implementato. La migration `m261008_130000_create_tbl_device_table` crea `tbl_device` con `name`, `game_id` (FK a `tbl_game`), `token_hash` e `is_enabled`. Il gestionale consente creazione, elenco e modifica di nome, tipologia e stato; il token viene generato una volta, mostrato al momento della creazione e salvato come hash. L'aggiornamento non modifica il token.

### Funzioni previste nel gestionale

- Creare un dispositivo con nome univoco, ad esempio `TavoloFreccette1`.
- Abilitare o disabilitare un dispositivo.
- Generare o rigenerare il token del dispositivo.
- Mostrare il token soltanto al momento della creazione o rigenerazione; nel database conservarne un hash.
- Selezionare un dispositivo abilitato durante la creazione della partita.
- Mostrare nel dettaglio della partita il dispositivo assegnato.

Il token è distinto dalle credenziali dei player. Ogni dispositivo usa il proprio token Bearer; non è previsto un token condiviso tra tutti i tavoli.

### Associazione dispositivo-partita

La proposta iniziale è di consentire **un solo dispositivo per partita** e **una sola partita attiva per dispositivo**. Una volta terminata la partita, il dispositivo può essere assegnato a una nuova partita. L'associazione storica alla partita conclusa resta consultabile.

Per collegare dispositivo e partita sarà necessaria una relazione persistente nel database, con vincolo che impedisca assegnazioni simultanee incompatibili. La forma precisa (FK su `tbl_matches` o tabella di associazione) sarà definita prima della migration.

## Accesso dei player

- L'amministratore assegna manualmente i player alla partita dal gestionale.
- I player non eseguono login nel gestionale e non devono avere token per partecipare.
- Non sono quindi previste API player per join o uscita dalla partita nella prima versione.
- Il client player può consultare partite e punteggi tramite endpoint di lettura. La visibilità pubblica e gli stati inclusi nell'elenco sono ancora da confermare.

## API candidate

Prefisso proposto: `/api/v1`.

| Metodo e percorso | Scopo | Autenticazione proposta |
| --- | --- | --- |
| `GET /api/v1/matches` | Elenco partite visibili al client player | Pubblica |
| `GET /api/v1/matches/{id}` | Dettaglio, stato, player e punteggi | Pubblica |
| `GET /api/v1/device/match` | Restituisce la partita attualmente assegnata al dispositivo autenticato | Bearer token del dispositivo |
| `POST /api/v1/device/throws` | Registra una freccetta per la partita assegnata | Bearer token del dispositivo |

La richiesta di tiro contiene solo i dati rilevati, ad esempio settore e moltiplicatore. Non contiene `user_id`, punteggio calcolato o player di turno: questi valori sono determinati e verificati dal server.

Risposta indicativa di un tiro accettato:

```json
{
  "throw": {
    "sector": 20,
    "multiplier": 3,
    "score": 60,
    "result": "scored"
  },
  "turn": {
    "dartsRemaining": 2,
    "playerToThrow": {
      "id": 42,
      "username": "player1",
      "remainingScore": 301
    }
  },
  "matchStatus": "RUNNING"
}
```

La risposta indica chi deve tirare dopo l'evento: il player può restare lo stesso per le freccette rimanenti della visita, oppure cambiare dopo tre freccette, un bust o la conclusione. I valori di `result` previsti sono `scored`, `missed`, `bust` e `checkout`. Nomi e forma definitivi del JSON saranno stabiliti con l'OpenAPI dopo le decisioni elencate sotto.

## Comunicazione e affidabilità

La prima versione usa HTTP/REST con richieste e risposte JSON. Dopo ogni freccetta il dispositivo invia un evento e riceve il nuovo stato; può richiedere nuovamente lo stato dopo una riconnessione. Non sono previsti WebSocket, MQTT o altri protocolli push nella prima versione.

È consigliato che ogni evento del dispositivo abbia un identificativo univoco. Se il dispositivo ripete una richiesta dopo un timeout, il server può riconoscere il duplicato e impedire che la stessa freccetta venga conteggiata due volte. Questo richiede di definire come persistere l'identificativo e sarà incluso nel disegno dello schema API/database.

## Errori HTTP indicativi

- `400 Bad Request`: payload malformato o settore/moltiplicatore non valido.
- `401 Unauthorized`: token del dispositivo mancante, errato o revocato.
- `404 Not Found`: partita o dispositivo inesistente.
- `409 Conflict`: partita non in corso, dispositivo senza partita assegnata, turno o transizione non valida, evento duplicato.
- `201 Created`: freccetta registrata.
- `200 OK`: lettura di stato o elenco completata.

Gli errori devono restituire JSON leggibile e coerente; la mappatura finale verrà specificata in OpenAPI.

## Decisioni ancora da confermare

1. Confermare il vincolo proposto di un solo dispositivo per partita attiva e una sola partita attiva per dispositivo.
2. Stabilire quali partite sono visibili senza autenticazione al client player (ad esempio solo `OPEN` e `RUNNING`, oppure anche quelle concluse).
3. Stabilire la politica per gli eventi duplicati e il formato dell'identificativo univoco del tiro.
4. Definire se il token del dispositivo viene mostrato una sola volta, come proposto, e come avvengono revoca e rigenerazione.
5. Definire i campi pubblici del dettaglio partita, in particolare se esporre username o solo nomi visualizzati.

## Sequenza di implementazione proposta

1. Completare il gestionale: selezione manuale dei player e assegnazione del dispositivo alla partita. Il gestore dispositivi base è già disponibile.
2. Definire eventuali migration necessarie per dispositivi, token e identificativi evento.
3. Implementare API versionate per lettura e ricezione freccette, riusando `DartsGameService`.
4. Scrivere `documentation/openapi.yaml` in base agli endpoint e ai payload confermati.
5. Collegare l'app separata del dispositivo alle API; mantenere il simulatore interno console come strumento diretto dei servizi.
