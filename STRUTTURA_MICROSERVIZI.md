# Pianificazione futura dell'architettura a microservizi

Questo documento descrive una possibile evoluzione futura della Connected Games Platform. **Non e la descrizione dell'architettura attualmente implementata.** L'obiettivo e separare l'applicazione Yii2 esistente in servizi autonomi, distribuibili e avviabili indipendentemente, ciascuno proprietario dei propri dati.

## Stato attuale

Il repository contiene un'unica applicazione Yii2 avviata come application server da PhpStorm. La gestione web, il controller API e i servizi applicativi condividono il progetto e la configurazione. Sono già presenti un'API per il dispositivo Freccette (`DartsApiController`) e la logica di gioco (`DartsGameService`, `DartsThrowCalculator`), ma **non sono microservizi separati**.

La configurazione di piu connessioni DB nella stessa app Yii2 non sarebbe sufficiente: per questa pianificazione, ogni servizio dovra essere un'applicazione/processo autonomo, con API e ciclo di rilascio indipendenti.

## Servizi proposti

La proposta comprende quattro applicazioni autonome sviluppate nel progetto. L'autenticazione amministrativa resta nella console Yii2 dedicata; un provider esterno come Keycloak potra essere valutato se emergeranno esigenze di accesso federato o di login player.

| Servizio | Responsabilita | Dati di proprieta |
| --- | --- | --- |
| **Console amministrativa (`admin-console`)** | Interfaccia web Yii2 per gli amministratori; login e sessione; coordinamento delle chiamate alle API di dominio. | Account e sessioni amministrative della console. Non possiede i dati di partite, dispositivi o gioco. |
| **Gestione partite (`matches-service`)** | API per creazione, apertura, avvio e chiusura delle partite; gestione dei partecipanti; associazione del dispositivo. | Partite, configurazione generale e partecipanti. Conserva gli identificativi esterni di giocatori e dispositivi, senza relazioni SQL verso i loro database. |
| **Dispositivi e IoT (`devices-service`)** | Registrazione, abilitazione e pairing dei dispositivi; autenticazione delle richieste provenienti da simulatore o dispositivo fisico. | Dispositivi e credenziali/token. I segreti non vengono restituiti dopo la creazione o il pairing previsto. |
| **Gioco Freccette (`darts-game-service`)** | Validazione dei tiri, regole 501, gestione dei turni, punteggi, bust e checkout; esposizione dello stato di gioco. | Tiri e stato di gioco necessario a ricostruire o consultare punteggio e turno. |

Per la prima versione non si introduce Keycloak: il login admin usa Yii2 nella `admin-console`, separata da `matches-service`. La console mantiene la sessione browser e funge da punto d'accesso alle viste amministrative; le API di dominio richiedono credenziali Bearer client-to-service distinte, come definito nella Fase 1. Un eventuale gateway API non e incluso nella prima fase.

## Proprieta e separazione dei database

Ogni servizio applicativo accede soltanto al proprio database. In ambiente di sviluppo i database possono risiedere sulla stessa istanza MySQL, ma devono avere schema/database distinti e credenziali con privilegi limitati. Keycloak usa il proprio database, per esempio PostgreSQL.

- `matches-service` e l'unico a leggere e modificare il database delle partite.
- `devices-service` e l'unico a leggere e modificare il database dei dispositivi.
- `darts-game-service` e l'unico a leggere e modificare il database di gioco.
- `admin-console` gestisce gli account admin e le sessioni Yii2 nella prima versione. Se in futuro si adotta Keycloak, solo Keycloak gestira il proprio archivio d'identita.
- Non si creano foreign key tra database appartenenti a servizi diversi. Si usano identificativi opachi e API/eventi.
- Nessun servizio condivide credenziali DB con un altro servizio.

La separazione implica che non esista una singola transazione SQL attraverso servizi e database diversi. Ogni servizio garantisce la coerenza dei propri dati; gli altri si aggiornano tramite chiamate o eventi e devono gestire ritardi, errori e ripetizioni.

## Comunicazione prevista

- **REST/JSON su HTTP** per comandi e interrogazioni che richiedono una risposta immediata, ad esempio creare una partita, verificare un dispositivo o leggere lo stato di gioco.
- **MQTT publish/subscribe** per eventi asincroni e aggiornamenti che interessano piu componenti. Il broker e infrastruttura condivisa, non un microservizio di dominio.
- **Sessione Yii2** per l'accesso alla console admin nella prima versione; autenticazione delle chiamate tra console e servizi da definire nei contratti. Per i dispositivi si usa un token dedicato verificato da `devices-service`; non si riutilizzano credenziali admin.

Eventi iniziali da definire e versionare:

- `MatchCreated`, `PlayersAssigned`, `MatchStarted` e `MatchEnded`, pubblicati da `matches-service`.
- `ThrowRegistered` e `GameFinished`, pubblicati da `darts-game-service`.
- Gli eventi devono includere almeno un identificativo evento, il tipo/versione, l'identificativo partita e la data; il consumatore deve poter riconoscere duplicati.

Il servizio di gioco deve ricevere il contesto necessario per iniziare (partita, partecipanti, ordine di turno e configurazione), senza leggere direttamente il DB partite. Il dispositivo invia i dati grezzi del tiro; e il servizio di gioco a determinare turno, punteggio ed esito. Il servizio dispositivi autentica il chiamante e il flusso inoltra il comando al servizio di gioco. Il dettaglio dei flussi e delle responsabilita API andra specificato prima dell'estrazione.

## Esecuzione locale

Il codice del progetto resta fuori dalle cartelle XAMPP. XAMPP fornisce Apache e MySQL; il database viene raggiunto su `localhost`. Il server applicativo viene avviato da PhpStorm e l'app è raggiungibile su `http://127.0.0.1:8080`. La root del repository può restare un **monorepo**: non è necessario dividere subito il codice in repository Git diversi.

Struttura obiettivo indicativa:

```text
connected_games_platform/
  services/
    admin-console/         # app Yii2, login/sessione admin e viste gestionali
    matches-service/       # app Yii2 avviata da PhpStorm
    devices-service/       # app Yii2 avviata da PhpStorm
    darts-game-service/    # app Yii2 avviata da PhpStorm
```

Servizi applicativi previsti:

| Processo | Porta host indicativa | Database |
| --- | ---: | --- |
| `admin-console` | `8080` | Account admin |
| `matches-service` | `8001` | MySQL XAMPP, database partite |
| `devices-service` | `8002` | MySQL XAMPP, database dispositivi |
| `darts-game-service` | `8003` | MySQL XAMPP, database gioco |

Le porte host sono indicative e si possono cambiare. In locale i processi comunicano via HTTP su `127.0.0.1`; ognuno accede solo al proprio database MySQL su `localhost`. Le applicazioni non creano database o utenti all'avvio.

Avviare Apache e MySQL dal pannello XAMPP e l'application server dalla configurazione PhpStorm del servizio interessato.

## Migrazione dal monolite

La migrazione si svolge in questo repository (monorepo), mantenendo inizialmente l'applicazione Yii2 attuale come riferimento e percorso funzionante. Ogni servizio estratto sara una propria applicazione Yii2, con entry point, configurazione, dipendenze, processo, migrazioni e database di proprieta. Non si inizia copiando tutto il progetto tre volte: si estrae una responsabilita per volta e si sostituiscono i collegamenti interni con contratti espliciti.

Le fasi sono sequenziali. Una fase si considera conclusa solo quando i suoi risultati sono verificabili e il flusso esistente continua a funzionare. Gli elementi `[ ]` sono caselle da spuntare durante il lavoro.

### Fase 0 — Rilievo e confini dei servizi

- [x] Inventariare tabelle, modelli, controller, comandi e flussi che scrivono o leggono ciascun dato.
- [x] Assegnare ogni dato a un proprietario e registrare gli identificativi che gli altri servizi possono conservare.
- [x] Definire la fonte autorevole per stato generale, partecipanti, punteggi, dispositivo e identita.
- [x] Segnare relazioni ActiveRecord e foreign key che attraversano i confini previsti; pianificare come sostituirle.
- [x] Confermare che la prima dimostrazione copra una partita 501 completa, dal pairing dispositivo al checkout, come nel monolite.

**Risultato:** mappa dati/flussi e confini approvati prima di spostare codice. La prima dimostrazione copre una partita 501 end-to-end.

#### Decisioni della Fase 0

- `matches-service` possiede la gestione generale delle partite e dei partecipanti. Gli amministratori gestiscono il gestionale; i player non accedono nella prima versione. Un'eventuale futura utenza player potra leggere soltanto i resoconti delle proprie partite.
- Il login admin e la sessione Yii2 appartengono a `admin-console`, applicazione autonoma separata da `matches-service`. Non si introduce Keycloak nella prima versione; potra essere valutato in seguito se cambiano i requisiti d'accesso.
- La console mostra le viste gestionali agli amministratori e coordina le chiamate alle API. Le API di dominio devono autenticare/limitare le chiamate server-to-server; il dettaglio del meccanismo e dei confini di rete va definito nella Fase 1, senza affidarsi alla sola visibilita delle pagine.
- `matches-service` e autorevole per esistenza, configurazione generale, stato amministrativo e partecipanti della partita; conserva gli ID opachi di giocatori e dispositivi. Non crea foreign key verso database di altri servizi.
- `devices-service` e proprietario del registro, abilitazione e credenziali dei dispositivi. L'associazione di un ID dispositivo a una partita e parte della gestione della partita (`matches-service`); la validita del dispositivo si verifica tramite API.
- `darts-game-service` e autorevole per regole e configurazione specifica di Freccette, ordine/turno di gioco, tiri, punteggi, bust e checkout. Riceve da `matches-service` ID partita, partecipanti e contesto necessario all'inizializzazione. Non legge il database partite.
- `matches-service` mantiene lo stato del ciclo generale (`CREATED`, `OPEN`, `RUNNING`, `FINISHED`). Il risultato di gioco e la conclusione per checkout appartengono a `darts-game-service`; la conclusione viene comunicata a `matches-service` tramite contratto API/evento.
- L'identita admin e l'identita giocatore sono concetti distinti. Per ora gli admin hanno credenziali Yii2 in `admin-console`; i profili player, se richiesti dal flusso, sono dati di dominio gestiti da `matches-service` senza credenziali. Un futuro login player dovra aggiungere un'autenticazione e un'associazione sicura al profilo player, limitando la lettura ai propri resoconti.

#### Inventario dati e proprietari obiettivo

| Dato/tabella legacy | Uso attuale rilevato | Proprietario obiettivo | Destino e note |
| --- | --- | --- | --- |
| `tbl_game` (`Game`) | Catalogo giochi; `GameMatch` e `Device` lo interrogano. | `matches-service` per il catalogo dei giochi attivabili. | `devices-service` usera un `game_code` opaco invece della FK locale. Regole e configurazione specifiche restano nel servizio del gioco. |
| `tbl_matches` (`GameMatch`, `DartsMatch`) | Nome, gioco, stato, capienza, date; associazione dispositivo; dashboard e pagine admin. | `matches-service` | `device_id` rimane un ID opaco. Stato generale gestito qui; gli esiti sportivi sono del game service. |
| `tbl_match_players` (`MatchPlayer`) | Partecipanti e user FK, ma anche `score` e `turn_order`; usata da gestione partita e logica/API Darts. | Diviso tra `matches-service` e `darts-game-service`. | `matches-service` conserva appartenenza e ID player; `darts-game-service` conserva ordine di gioco e stato/punteggio. La migrazione dovrà popolare il game service e poi rimuovere queste colonne dal database matches. |
| `tbl_throws` (`DartThrow`) | Tiri e riferimenti FK a partita e utente; scritta dalla logica di gioco e letta da viste/API. | `darts-game-service` | `match_id` e `player_id` diventano riferimenti opachi, senza FK verso matches o identità. |
| `tbl_user` (`User`) | Tabella mista: account admin/player, password hash, ruolo; relazione con partecipanti e tiri. | Separato per responsabilità. | Gli account admin migrano nel database di `admin-console`; i profili player, se mantenuti, in `matches-service` senza password. Il `player_id` stabile è usato dal gioco. |
| `tbl_device` (`Device`) | Registro dispositivi, game FK, abilitazione, hash token e token cifrato; consultata da UI/API. | `devices-service` | Spostare registro e segreti. Il token non deve essere restituito dopo la fase autorizzata di provisioning; gli altri servizi usano l'API device. |
| `sys_autoconfigurazione` (`Autoconfigurazione`) | Impostazioni UI, attualmente tema chiaro/scuro. | Da assegnare con la console admin. | Se resta configurazione della console, appartiene al frontend/servizio che lo ospita; non è dato di dominio di gioco. Decidere durante l'estrazione della console. |

#### Flussi e accoppiamenti rilevati

| Flusso attuale | Letture/scritture rilevate | Confine da applicare |
| --- | --- | --- |
| Creazione e modifica partita | `DartsMatchController` scrive `tbl_matches`, legge catalogo `tbl_game` e dispositivi `tbl_device`. | Comando a `matches-service`; verifica dispositivo tramite `devices-service`. |
| Assegnazione/rimozione player | `DartsMatchController` consulta `tbl_user`, `DartsGameService` scrive `tbl_match_players`; la UI legge la stessa tabella. | Appartenenza gestita da `matches-service`; invio del roster aggiornato al game service quando serve, senza condivisione DB. |
| Apertura/avvio/termine | `GameMatch::transitionTo` aggiorna stato; API e logica di gioco leggono/scrivono `tbl_matches.status`. | Transizioni generali in `matches-service`; inizializzazione e conclusione gioco via contratto con `darts-game-service`. |
| Tiro e stato Freccette | `DartsApiController` autentica token leggendo `tbl_device`, poi legge partita/player e `DartsGameService` scrive tiri, punteggi e stato in tabelle condivise. | Autenticazione device via `devices-service`; comando tiro al `darts-game-service`; nessuna query diretta ai DB matches/devices. |
| Login e pagine amministrative | Login Yii usa `User`; `AdminController` protegge dashboard e viste gestionali. | Login/sessione migrati in `admin-console`; la console chiama API di dominio protette con credenziali server-to-server da definire. Player senza login nella prima versione. |

#### Relazioni da rimuovere ai confini

- `tbl_matches.game_id → tbl_game.id`: relazione interna a `matches-service`, può restare locale.
- `tbl_matches.device_id → tbl_device.id`: attraversa il confine matches/devices; sostituire FK/ActiveRecord con ID opaco e verifica API.
- `tbl_match_players.match_id → tbl_matches.id`: relazione interna a matches per l'appartenenza; il game service riceve `match_id` opaco.
- `tbl_match_players.user_id` e `tbl_throws.user_id → tbl_user.id`: eliminare dipendenza SQL/ActiveRecord; usare un `player_id` stabile e non joinare il database d'identità.
- `tbl_throws.match_id → tbl_matches.id`: rimuovere la FK nel game service; validare partita/stato tramite contesto e contratto di inizializzazione.
- `tbl_device.game_id → tbl_game.id`: sostituire con `game_code` o identificatore opaco, verificato secondo il contratto concordato.

La ricognizione è basata sul codice Yii2 e sulla descrizione dello schema nel file `FLUSSO_LAVORO_CGP.md`; le migrazioni presenti in questo repository coprono solo `sys_autoconfigurazione` e `tbl_device`. Prima di migrare i dati, verificare sul database reale colonne, vincoli e stato effettivo di `tbl_matches.device_id`.

### Fase 1 — Contratti e flussi

- [x] Disegnare le sequenze di creazione/apertura/avvio partita, pairing, tiro e chiusura.
- [x] Definire API REST con richieste, risposte, codici errore, autenticazione, timeout e versionamento; documentarle in OpenAPI.
- [x] Definire identificativi stabili e payload/versioni degli eventi MQTT, oltre a deduplicazione e gestione dei messaggi non elaborabili.
- [x] Specificare idempotenza dei comandi che possono essere ritentati, soprattutto registrazione tiro e transizioni partita.
- [x] Definire le regole di compatibilita durante la migrazione: chi instrada ogni richiesta e come si evita la doppia scrittura.

**Risultato:** contratti consultabili e un flusso end-to-end documentato in [`docs/openapi.yaml`](docs/openapi.yaml) e in questo documento. La prima versione usa HTTP per tutte le operazioni necessarie alla partita; MQTT viene introdotto quando un consumer asincrono concreto lo richiede.

#### Decisioni della Fase 1

- **Autenticazione:** traffico HTTP solo su TLS fuori dallo sviluppo locale. Le chiamate console-servizi e servizio-servizio usano credenziali Bearer distinte per client, con ambiti/permessi minimi, configurate via secret e ruotabili. Le API rifiutano richieste senza credenziale valida (401) e credenziali valide ma prive del permesso richiesto (403). Non si usa la sessione browser admin per chiamate server-to-server, né si introduce Keycloak nella prima versione. Il pairing/provisioning del device è un'operazione admin autenticata; il token device viene mostrato una sola volta e non rilasciato da un endpoint anonimo di selezione.
- **Versionamento e formato:** prefisso `/api/v1`; JSON UTF-8; date UTC in formato RFC 3339; identificativi di risorsa stabili e opachi per gli altri servizi. Gli errori hanno forma `{ "error": { "code": "...", "message": "...", "correlationId": "..." } }`. Le risposte includono `correlationId`; le chiamate inoltrate propagano lo stesso ID.
- **Timeout e retry:** timeout connessi brevi e configurabili (2 s per connect, 5 s per risposta nel flusso interattivo). Retry limitati con backoff solo su timeout/errore temporaneo e solo per operazioni idempotenti; errori 4xx non si ritentano. Nessun client deve interpretare un timeout di scrittura come prova che l'operazione non sia stata applicata.
- **Comandi ripetibili:** ogni comando mutante ritentabile porta `Idempotency-Key` (UUID generato dal chiamante). Il servizio memorizza chiave, ambito, hash richiesta e risultato nella stessa transazione della modifica; stessa chiave/stesso contenuto restituisce il risultato originale, stessa chiave/contenuto diverso dà 409. La registrazione di un tiro non viene ritentata senza chiave. Le transizioni partita usano la stessa chiave e transizioni ripetute verso lo stato già raggiunto restituiscono lo stato corrente senza effetti collaterali.
- **Flusso iniziale sincrono:** `admin-console` crea la partita e assegna partecipanti/dispositivo via `matches-service`; quest'ultimo verifica il device via `devices-service`. All'avvio, `matches-service` invia al gioco un contesto completo e versionato (match ID, gioco/configurazione, roster ordinato) e solo dopo l'inizializzazione riuscita porta la partita a `RUNNING`. Il device autenticato invia il tiro al `devices-service`, che autentica e inoltra il comando al `darts-game-service`; la risposta del gioco è l'esito autorevole. A checkout il gioco notifica `matches-service` con una chiamata idempotente per chiudere lo stato generale. Una dipendenza indisponibile produce errore temporaneo (503/504) e stato non avanzato; non si fa doppia scrittura nei database.
- **Eventi:** MQTT non è prerequisito della prima estrazione. Quando introdotti, eventi in envelope versionato con `eventId` UUID, `eventType`, `eventVersion`, `occurredAt`, `correlationId`, `matchId` e `payload`; outbox transazionale presso il publisher, deduplicazione persistente presso il consumer, retry limitati e coda/registro per messaggi non elaborabili. Le notifiche non sostituiscono le API sincrone per le decisioni che richiedono esito immediato.
- **Compatibilità/rollback:** in ogni fase un solo percorso è autorevole per le scritture di ciascun dato. Il traffico passa al nuovo proprietario solo dopo migrazione e riconciliazione; rollback instrada nuovamente al vecchio percorso usando una finestra di compatibilità esplicitamente mantenuta. Non si fanno dual-write. Le letture possono essere confrontate in shadow mode, senza effetti collaterali.

#### Sequenza e responsabilità del flusso 501

1. L'admin crea la partita e assegna player/dispositivo: la console chiama `matches-service`; quest'ultimo persiste i propri dati e verifica l'abilitazione del device con `devices-service`.
2. L'admin apre la partita e avvia il gioco. `matches-service` invia roster e configurazione a `darts-game-service` con una chiave idempotente. Se il gioco non conferma l'inizializzazione, la partita non diventa `RUNNING`.
3. Il simulatore ottiene il proprio token tramite pairing amministrativo. Il dispositivo interroga lo stato presso `devices-service`; il token identifica il device, non un utente admin.
4. Il device invia settore e moltiplicatore più `Idempotency-Key` a `devices-service`. Quest'ultimo valida il token e inoltra la richiesta al gioco con identità device verificata; il client non sceglie player, punteggio o turno.
5. `darts-game-service` valida stato, turno e regole, registra tiro e aggiornamento in una transazione e restituisce lo stato aggiornato. Al checkout notifica `matches-service`; in caso di indisponibilità conserva la conclusione autorevole e riprova la notifica tramite outbox.

Interfacce principali, tutte sotto `/api/v1` e descritte in OpenAPI: `POST /matches`, `POST /matches/{matchId}/players`, `PUT /matches/{matchId}/device`, `POST /matches/{matchId}/open`, `POST /matches/{matchId}/start`, `POST /matches/{matchId}/finish`; `POST /devices/{deviceId}/pair` (admin), `GET /device-session/state` e `POST /device-session/throws` (device); `PUT /games/darts/matches/{matchId}/context`, `GET /games/darts/matches/{matchId}/state`, `POST /games/darts/matches/{matchId}/throws`, `POST /matches/{matchId}/finished` (callback autenticata). Transizioni in conflitto danno 409, input invalido 400/422, risorsa assente 404, credenziali 401/403 e dipendenza non disponibile 503/504.

### Fase 2 — Rendere riproducibile l'ambiente locale

- [x] Aggiungere la struttura `services/` per ospitare in seguito le applicazioni Yii2 autonome, senza duplicare ora il monolite.
- [x] Documentare il runtime locale: Apache e MySQL sono avviati da XAMPP; l'application server è avviato da PhpStorm.
- [x] Tenere il codice fuori dalle cartelle XAMPP e collegare Yii2 a MySQL su `localhost` con la configurazione del progetto.
- [x] Definire che ogni servizio avrà database e credenziali dedicati, creati e gestiti separatamente nell'ambiente XAMPP; le applicazioni non provisionano database o utenti.
- [x] Rinviare MQTT: la Fase 1 ha stabilito che non serve al primo flusso sincrono.

**Risultato:** il codice resta nel repository, XAMPP fornisce Apache e MySQL, e il server applicativo viene avviato da PhpStorm su `http://127.0.0.1:8080`. I database dei servizi estratti restano responsabilità dell'ambiente XAMPP e useranno account distinti.

Per l'avvio locale e la configurazione del database, vedere la sezione XAMPP del `README.md`.

### Fase 3 — Estrarre `darts-game-service`

- [x] Creare l'applicazione Yii2 autonoma del gioco con endpoint di inizializzazione, stato e registrazione tiro.
- [x] Trasferire calcolatore, regole 501, tiri e stato di gioco nel servizio e nelle sue migrazioni.
- [x] Fare ricevere al gioco il contesto necessario (ID partita, partecipanti, ordine e configurazione) attraverso il contratto definito, mai dal DB partite.
- [x] Rendere la registrazione del tiro atomica nel DB del gioco e idempotente verso retry.
- [x] Sostituire il percorso di avvio e registrazione tiro con HTTP dal monolite; mantenere il percorso legacy per partite senza contesto nel nuovo servizio e non fare fallback dopo un esito remoto incerto.
- [ ] Verificare partite nuove e, se richiesto, migrare partite in corso/storiche con conteggi e riconciliazione dei punteggi.

**Risultato:** codice e database del gioco sono autonomi. La verifica pratica resta da fare dopo aver creato `connected_games_dartsgame` in XAMPP, applicato la migrazione e configurato i token nei run configuration PhpStorm. Le partite senza contesto nel nuovo servizio continuano temporaneamente sul percorso legacy.

### Fase 4 — Estrarre `devices-service`

- [ ] Creare applicazione Yii2 e database del servizio per registrazione, abilitazione, pairing e credenziali device.
- [ ] Spostare autenticazione dispositivo e gestione dei segreti fuori da `DartsApiController`.
- [ ] Fare autenticare il dispositivo presso questo servizio e inoltrare i comandi di gioco secondo il contratto stabilito.
- [ ] Ruotare/migrare i token esistenti in modo controllato; conservare solo hash dove non serve recuperare il segreto.
- [ ] Verificare dispositivo disabilitato, token non valido, pairing e retry di richieste.

**Risultato:** nessun altro servizio legge token o tabelle device; il simulatore usa il nuovo flusso.

### Fase 5 — Estrarre `matches-service` e interfaccia amministrativa

- [ ] Creare l'app Yii2 per partite, configurazione, partecipanti e interfaccia amministrativa.
- [ ] Spostare qui le relative tabelle e le transizioni di stato; sostituire foreign key/relazioni SQL verso device, utenti e dati di gioco con identificativi e API/eventi.
- [ ] Sostituire gli accessi al gioco e ai dispositivi con i contratti delle fasi precedenti.
- [ ] Gestire l'inizializzazione del gioco quando una partita viene avviata e le risposte parziali/fallimenti delle dipendenze.
- [ ] Verificare il flusso amministrativo completo e l'accesso ai risultati senza query dirette ai database altrui.

**Risultato:** gestione partita e gioco sono processi distinti; ciascuno legge e scrive soltanto i propri dati.

### Fase 6 — Identita e autorizzazioni

- [ ] Migrare login e account admin alla `admin-console` Yii2, con gestione sicura delle password e sessioni.
- [ ] Definire e applicare autenticazione/autorizzazione delle chiamate server-to-server tra console e servizi.
- [ ] Distinguere sessioni admin, credenziali tra servizi e token dei dispositivi.
- [ ] Verificare accesso negato alle API senza credenziali o con credenziali non valide.
- [ ] Valutare Keycloak solo se servono accessi federati o account player autenticati.

**Risultato:** console admin e API di dominio sono protette con credenziali appropriate; nessun servizio di dominio gestisce il login admin.

### Fase 7 — Affidabilita, dati e rilascio

- [ ] Aggiungere log strutturati con correlation ID e identificativi partita/evento attraverso tutti i servizi.
- [ ] Definire limiti di retry, timeout, circuitazione dove utile e comportamento in caso di dipendenza non disponibile.
- [ ] Se si pubblicano eventi dopo una scrittura DB, definire come evitare perdita o doppia pubblicazione (per esempio outbox e consumer idempotenti).
- [ ] Documentare migrazioni e rollback per ogni servizio, backup/restore e prova di ripristino.
- [ ] Separare pipeline/build, configurazioni e versione distribuibile di ogni applicazione; mantenere versioni compatibili dei contratti.
- [ ] Eseguire il flusso end-to-end e riconciliare dati e risultati prima di spostare traffico definitivo.

**Risultato:** servizi osservabili e recuperabili, con procedura ripetibile di distribuzione e rollback.

### Fase 8 — Completare il passaggio e rimuovere il legacy

- [ ] Spostare traffico per fasi e monitorare errori e coerenza dei dati; stabilire un criterio di rollback.
- [ ] Fare un backup verificato e completare la riconciliazione dei dati prima della rimozione delle tabelle legacy.
- [ ] Rimuovere controller, modelli, tabelle e configurazioni monolitiche solo dopo il passaggio stabile e verificato.
- [ ] Aggiornare documentazione, istruzioni operative e diagrammi allo stato realmente implementato.

**Risultato:** flusso operativo servito dai servizi autonomi; il monolite non contiene piu percorsi attivi o dati di proprieta altrui.

### Regole di avanzamento

- Procedere una fase per volta e aggiornare le caselle e lo stato in questo documento al termine di ogni sessione.
- Ogni fase deve lasciare il sistema avviabile e fornire un percorso di rollback finche la migrazione non e consolidata.
- Non effettuare doppie scritture indipendenti come soluzione transitoria senza una strategia di riconciliazione.
- Non eliminare dati legacy finche backup, conteggi e controlli di coerenza non sono stati verificati.
- La prima fase operativa e la Fase 0; non si crea ancora una seconda applicazione finche proprieta dei dati e flussi non sono chiari.

## Flusso-obiettivo della partita

### Decisioni consolidate

- L'autenticazione server-to-server e i contratti REST sono definiti nella Fase 1 e nello schema OpenAPI. La Fase 2 documenta l'ambiente XAMPP + PhpStorm. L'estrazione operativa in corso è la Fase 3.

1. L'amministratore si autentica nella `admin-console` Yii2, crea la partita in `matches-service` e assegna player e dispositivo.
2. `matches-service` verifica l'abilitazione del dispositivo tramite API di `devices-service` e pubblica gli eventi di assegnazione/avvio.
3. `darts-game-service` riceve il contesto della partita e prepara il proprio stato di gioco.
4. Il simulatore si autentica con `devices-service` e invia un tiro.
5. Il tiro arriva a `darts-game-service`, che verifica stato, turno e regole, salva il tiro nel proprio DB, aggiorna punteggio e turno e restituisce il nuovo stato.
6. `darts-game-service` pubblica l'evento di tiro/conclusione. I servizi interessati aggiornano la propria vista o notificano i client.
7. Il player consulta le partite e i punteggi attraverso API autorizzate, senza accesso ai database interni.

## Ambito iniziale e criteri di completamento

Per contenere la complessita, la prima dimostrazione dovrebbe includere il flusso minimo: autenticazione, creazione e avvio partita, pairing dispositivo, tiro valido/non valido, aggiornamento del punteggio e conclusione. Non occorre implementare subito classifiche, notifiche push o gateway.

La separazione puo dirsi realizzata quando:

- ogni servizio applicativo si avvia come processo distinto e puo essere aggiornato indipendentemente dagli altri;
- ogni servizio possiede un DB/schema e credenziali dedicati e nessun servizio accede direttamente ai DB altrui;
- le interazioni tra servizi passano da contratti HTTP o messaggi documentati;
- il flusso end-to-end funziona avviando i processi da PhpStorm e usando i database predisposti in XAMPP;
- migrations e backup sono gestiti separatamente per ogni database.

## Riferimenti al materiale del corso

- `D:\Universita\Vercelli\PISSIR\laboratorio\2 The MicroServices architecture style.pdf`: servizi autonomi/eseguibili, API, decomposizione e comunicazione tra componenti.
- `D:\Universita\Vercelli\PISSIR\laboratorio\3 - REST HTTP Servers- nozioni di base.pdf`: risorse, URI, metodi HTTP e rappresentazioni JSON.
- `D:\Universita\Vercelli\PISSIR\laboratorio\5 - Modello Publish_Subscribe- introduzione e componenti.pdf`, `6 - Mosquitto- uso e configurazione.pdf` e `9 Arduino wi-fi e MQTT.pdf`: publish/subscribe e MQTT per la comunicazione IoT.
- `D:\Universita\Vercelli\PISSIR\laboratorio\7 Oauth2 e Keycloak.pdf`: authorization server, OAuth 2.0, Keycloak e JWT.

