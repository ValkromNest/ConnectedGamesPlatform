# Pianificazione futura dell'architettura a microservizi

Questo documento descrive una possibile evoluzione futura della Connected Games Platform. **Non e la descrizione dell'architettura attualmente implementata.** L'obiettivo e separare l'applicazione Yii2 esistente in servizi autonomi, distribuibili e avviabili indipendentemente, ciascuno proprietario dei propri dati.

## Stato attuale

Il repository contiene un'unica applicazione Yii2 avviata come application server da PhpStorm. La gestione web, il controller API e i servizi applicativi condividono il progetto e la configurazione. Sono già presenti un'API per il dispositivo Freccette (`DartsApiController`) e la logica di gioco (`DartsGameService`, `DartsThrowCalculator`), ma **non sono microservizi separati**.

La configurazione di piu connessioni DB nella stessa app Yii2 non sarebbe sufficiente: per questa pianificazione, ogni servizio dovra essere un'applicazione/processo autonomo, con API e ciclo di rilascio indipendenti.

## Servizi proposti

La struttura comprende tre applicazioni: `identity-service` per autenticazione e account, l'app principale (Yii2) per la gestione amministrativa e di dominio, e `darts-game-service` per le regole e lo stato specifico delle Freccette. Partite e dispositivi non diventano servizi autonomi. Il servizio identità dedicato espone un'API interna con token bearer opachi; non implementa OpenID Connect.

| Servizio | Responsabilita | Dati di proprieta |
| --- | --- | --- |
| **Servizio identita (`identity-service`)** | Autenticazione e ciclo di vita degli account admin/player; emissione e rinnovo delle credenziali di accesso. | Credenziali, identificativi soggetto, ruoli e sessioni/token. Non possiede profili di dominio, partite o dispositivi. |
| **Applicazione principale (`connected_games_platform`)** | Interfaccia web Yii2; gestione partite, partecipanti e dispositivi; autorizzazione alle funzioni e ai resoconti; coordinamento del servizio di gioco. | Dati di dominio di partite, partecipanti e dispositivi. I partecipanti mantengono subject ID e snapshot del nome, senza una copia locale degli account. |
| **Gioco Freccette (`darts-game-service`)** | Validazione dei tiri, regole 501, gestione dei turni, punteggi, bust e checkout; esposizione dello stato di gioco. | Tiri e stato di gioco necessario a ricostruire o consultare punteggio e turno. |

Nell'implementazione attuale l'identity service è la fonte degli account
player: il monolite li legge tramite API e salva nella partecipazione solo il
subject ID e uno snapshot del nome. Non mantiene una copia locale degli account.

L'applicazione principale non gestisce password o autenticazione: delega il login a `identity-service` e usa l'identità risultante per autorizzare le proprie funzioni. Il servizio di gioco accetta solo chiamate server-to-server con credenziali distinte. Un eventuale gateway API non e incluso nella prima fase.

## Proprieta e separazione dei database

Ogni servizio applicativo accede soltanto al proprio database. In ambiente di sviluppo i database possono risiedere sulla stessa istanza MySQL, ma devono avere schema/database distinti e credenziali con privilegi limitati. Il database dell'identity service dipende dall'implementazione scelta.

- `identity-service` e l'unico componente a leggere e modificare il proprio archivio di credenziali, sessioni/token e ruoli.
- `connected_games_platform` e l'unica applicazione a leggere e modificare i dati di dominio del monolite: partite, partecipanti e dispositivi.
- `darts-game-service` e l'unico a leggere e modificare il database di gioco.
- L'app principale non conserva password o sessioni di autenticazione; usa il subject ID restituito dall'identity service e conserva il nome visualizzato nella partecipazione.
- Non si creano foreign key tra database appartenenti a servizi diversi. Si usano identificativi opachi e API/eventi.
- Nessun servizio condivide credenziali DB con un altro servizio.

La separazione implica che non esista una singola transazione SQL attraverso servizi e database diversi. Ogni servizio garantisce la coerenza dei propri dati; gli altri si aggiornano tramite chiamate o eventi e devono gestire ritardi, errori e ripetizioni.

## Comunicazione prevista

- **REST/JSON su HTTP** per le richieste dal monolite al servizio di gioco, come inizializzare una partita, registrare un tiro o leggere lo stato.
- **MQTT publish/subscribe** per eventi asincroni e aggiornamenti che interessano piu componenti. Il broker e infrastruttura condivisa, non un microservizio di dominio.
- **Autenticazione** tramite API interna di `identity-service`: login e logout via POST, token bearer opaco conservato dal monolite in sessione server e convalidato tramite endpoint `/me`. L'app principale applica autorizzazioni alle proprie risorse e ai resoconti. Le chiamate dal monolite al servizio di gioco usano credenziali server-to-server distinte; i token dei dispositivi restano distinti dalle credenziali utente.

Eventi iniziali da definire e versionare:

- `MatchCreated`, `PlayersAssigned`, `MatchStarted` e `MatchEnded`, pubblicati dal monolite se e quando sara introdotto un consumer asincrono concreto.
- `ThrowRegistered` e `GameFinished`, pubblicati da `darts-game-service`.
- Gli eventi devono includere almeno un identificativo evento, il tipo/versione, l'identificativo partita e la data; il consumatore deve poter riconoscere duplicati.

Il servizio di gioco deve ricevere il contesto necessario per iniziare (partita, partecipanti, ordine di turno e configurazione), senza leggere direttamente il DB del monolite. Gli utenti si autenticano tramite `identity-service`; il monolite applica i permessi sui propri dati. Il dispositivo invia i dati grezzi del tiro all'API del monolite, che li inoltra al servizio di gioco, responsabile di turno, punteggio ed esito.

## Esecuzione locale

Il codice del progetto resta fuori dalle cartelle XAMPP. XAMPP fornisce Apache e MySQL; il database viene raggiunto su `localhost`. Il server applicativo viene avviato da PhpStorm e l'app è raggiungibile su `http://127.0.0.1:8080`. La root del repository può restare un **monorepo**: non è necessario dividere subito il codice in repository Git diversi.

Struttura attuale e obiettivo indicativa:

```text
connected_games_platform/
  config/                  # configurazione Yii2 del monolite
  controllers/             # interfaccia admin e API dispositivo
  models/                  # partite, partecipanti, dispositivi e account
  services/
    identity-service/      # autenticazione interna e account admin/player
    darts-game-service/    # app Yii2 autonoma avviata da PhpStorm
```

Servizi applicativi previsti:

| Processo | Porta host indicativa | Database |
| --- | ---: | --- |
| `connected_games_platform` (monolite) | `8080` | MySQL XAMPP, database `connected_games_db` |
| `identity-service` | `8001` | Database identita dedicato |
| `darts-game-service` | `8003` | MySQL XAMPP, database `connected_games_dartsgame` |

Le porte host sono indicative e si possono cambiare. In locale l'app principale comunica via HTTP con l'identity service e il servizio di gioco su `127.0.0.1`; ciascun servizio accede soltanto al proprio database. Le applicazioni non creano database o utenti all'avvio.

Avviare Apache e MySQL dal pannello XAMPP e l'application server dalla configurazione PhpStorm del servizio interessato.

## Migrazione dal monolite

La migrazione si svolge in questo repository (monorepo), mantenendo l'applicazione Yii2 come app principale per interfaccia, partite e dispositivi. `identity-service` possiede autenticazione e credenziali; `darts-game-service` possiede le regole e lo stato delle Freccette. Ogni servizio autonomo ha entry point, configurazione, dipendenze, processo e archivio dati di proprieta. Non si estraggono servizi distinti per partite o dispositivi.

Le fasi sono sequenziali. Una fase si considera conclusa solo quando i suoi risultati sono verificabili e il flusso esistente continua a funzionare. Gli elementi `[ ]` sono caselle da spuntare durante il lavoro.

### Fase 0 — Rilievo e confini dei servizi

- [x] Inventariare tabelle, modelli, controller, comandi e flussi che scrivono o leggono ciascun dato.
- [x] Assegnare ogni dato a un proprietario e registrare gli identificativi che gli altri servizi possono conservare.
- [x] Definire la fonte autorevole per stato generale, partecipanti, punteggi, dispositivo e identita.
- [x] Segnare relazioni ActiveRecord e foreign key che attraversano i confini previsti; pianificare come sostituirle.
- [x] Confermare che la prima dimostrazione copra una partita 501 completa, dal pairing dispositivo al checkout, come nel monolite.

**Risultato:** mappa dati/flussi e confini approvati prima di spostare codice. La prima dimostrazione copre una partita 501 end-to-end.

#### Decisioni della Fase 0

- Il monolite possiede la gestione generale delle partite, dei partecipanti e dei dispositivi. Gli account e le credenziali admin/player appartengono all'identity service. I player non accedono nella prima versione; in futuro potranno leggere soltanto i propri resoconti.
- `identity-service` e autorevole per autenticazione, credenziali e ruoli degli account admin/player; l'implementazione va scelta prima dello sviluppo.
- Il monolite mostra le viste gestionali, gestisce i dati di dominio e le autorizzazioni sulle proprie risorse, e coordina le chiamate al servizio di gioco. Non conserva password.
- Il monolite e autorevole per esistenza, configurazione generale, stato amministrativo, partecipanti e dispositivo associato.
- Il registro, abilitazione, pairing e credenziali dei dispositivi restano nel monolite. Il token device viene autenticato dalla sua API.
- `darts-game-service` e autorevole per regole e configurazione specifica di Freccette, ordine/turno di gioco, tiri, punteggi, bust e checkout. Riceve dal monolite ID partita, partecipanti e contesto necessario all'inizializzazione. Non legge il database del monolite.
- Il monolite mantiene lo stato del ciclo generale (`CREATED`, `OPEN`, `RUNNING`, `FINISHED`). Il risultato sportivo e autorevole in `darts-game-service`; il monolite aggiorna lo stato generale in base alla risposta del servizio.
- Admin e player hanno ruoli distinti nell'identity service. I risultati restano nel monolite con subject ID e snapshot del nome; il monolite limita ciascun player ai propri resoconti.

#### Inventario dati e proprietari obiettivo

| Dato/tabella legacy | Uso attuale rilevato | Proprietario obiettivo | Destino e note |
| --- | --- | --- | --- |
| `tbl_game` (`Game`) | Catalogo giochi; `GameMatch` e `Device` lo interrogano. | Monolite | Resta nel monolite; il servizio riceve il codice del gioco nel contesto d'inizializzazione. |
| `tbl_matches` (`GameMatch`, `DartsMatch`) | Nome, gioco, stato, capienza, date; associazione dispositivo; dashboard e pagine admin. | Monolite | Resta nel monolite. `device_id` e ID partita sono inviati come identificativi opachi al servizio di gioco. |
| `tbl_match_players` (`MatchPlayer`) | Partecipanti con subject ID stabile e nome snapshot, `score` e `turn_order`; usata da gestione partita e API Darts. | Monolite per roster; servizio di gioco per stato runtime e punteggi | Il monolite gestisce l'appartenenza alla partita; il servizio riceve roster e ordine all'avvio e mantiene il punteggio di gioco. |
| `tbl_throws` (`DartThrow`) | Tiri e riferimenti FK a partita e utente; scritta dalla logica di gioco e letta da viste/API. | `darts-game-service` | `match_id` e `player_id` diventano riferimenti opachi, senza FK verso matches o identità. |
| `tbl_user` (`User`) | Tabella legacy mista: account admin/player, password hash, ruolo; non più consultata dal monolite a runtime. | Account e ruoli gestiti dall'identity service; nessuna copia runtime nel monolite | L'identity service fornisce l'elenco player agli admin. `tbl_match_players` conserva subject ID stabile e nome visualizzato alla partecipazione; il servizio di gioco riceve gli stessi dati senza accedere a questi archivi. `tbl_user` resta solo sorgente temporanea per l'import legacy e potrà essere rimossa in una fase successiva. |
| `tbl_device` (`Device`) | Registro dispositivi, game FK, abilitazione, hash token e token cifrato; consultata da UI/API. | Monolite | Resta nel monolite. Il token non deve essere restituito dopo il provisioning autorizzato; il servizio di gioco riceve solo l'identificativo del dispositivo. |
| `sys_autoconfigurazione` (`Autoconfigurazione`) | Impostazioni UI, attualmente tema chiaro/scuro. | Da assegnare con la console admin. | Se resta configurazione della console, appartiene al frontend/servizio che lo ospita; non è dato di dominio di gioco. Decidere durante l'estrazione della console. |

#### Flussi e accoppiamenti rilevati

| Flusso attuale | Letture/scritture rilevate | Confine da applicare |
| --- | --- | --- |
| Creazione e modifica partita | `DartsMatchController` scrive `tbl_matches`, legge catalogo `tbl_game` e dispositivi `tbl_device`. | Gestite dal monolite; il dispositivo viene verificato nel suo database. |
| Assegnazione/rimozione player | Il monolite legge via API i player abilitati da `identity-service`; `tbl_match_players` salva subject e nome visualizzato. | Gestita dal monolite; all'avvio il roster viene inviato al servizio di gioco senza condividere il database. |
| Apertura/avvio/termine | `GameMatch::transitionTo` aggiorna stato; il servizio di gioco gestisce il runtime della partita. | Il monolite gestisce transizioni generali; inizializzazione e stato sportivo passano via API a `darts-game-service`. |
| Tiro e stato Freccette | `DartsApiController` autentica token leggendo `tbl_device`; `DartsGameServiceClient` inoltra le operazioni di gioco. | Autenticazione device e associazione alla partita nel monolite; regole e punteggi in `darts-game-service`, senza accesso al DB monolitico. |
| Login e pagine amministrative | Login Yii usa `User`; `AdminController` protegge dashboard e viste gestionali. | Login delegato all'identity service; il monolite mantiene interfaccia e autorizzazioni sulle proprie risorse. |

#### Relazioni da rimuovere ai confini

- `tbl_matches.game_id → tbl_game.id`: relazione interna al monolite, può restare locale.
- `tbl_matches.device_id → tbl_device.id`: relazione interna al monolite, può restare locale.
- `tbl_match_players.match_id → tbl_matches.id`: relazione interna al monolite per il roster; il game service riceve `match_id` opaco.
- `tbl_match_players.identity_subject_id` non ha FK cross-service; il nome visualizzato è uno snapshot al momento dell'assegnazione. Il servizio di gioco riceve il subject ID stabile e non interroga il database d'identità.
- `tbl_throws.match_id → tbl_matches.id`: rimuovere la FK nel game service; validare partita/stato tramite contesto e contratto di inizializzazione.
- `tbl_device.game_id → tbl_game.id`: relazione interna al monolite; al servizio di gioco si invia il codice del gioco nel contesto.

La ricognizione è basata sul codice Yii2 e sulla descrizione dello schema nel file `FLUSSO_LAVORO_CGP.md`; le migrazioni presenti in questo repository coprono solo `sys_autoconfigurazione` e `tbl_device`. Prima di migrare i dati, verificare sul database reale colonne, vincoli e stato effettivo di `tbl_matches.device_id`.

### Fase 1 — Contratti e flussi

- [x] Disegnare le sequenze di creazione/apertura/avvio partita, pairing, tiro e chiusura.
- [x] Definire API REST con richieste, risposte, codici errore, autenticazione, timeout e versionamento; documentarle in OpenAPI.
- [x] Definire identificativi stabili e payload/versioni degli eventi MQTT, oltre a deduplicazione e gestione dei messaggi non elaborabili.
- [x] Specificare idempotenza dei comandi che possono essere ritentati, soprattutto registrazione tiro e transizioni partita.
- [x] Definire le regole di compatibilita durante la migrazione: chi instrada ogni richiesta e come si evita la doppia scrittura.

**Risultato:** contratti consultabili e un flusso end-to-end documentato in [`docs/openapi.yaml`](docs/openapi.yaml) e in questo documento. La prima versione usa HTTP per tutte le operazioni necessarie alla partita; MQTT viene introdotto quando un consumer asincrono concreto lo richiede.

#### Decisioni della Fase 1

- **Autenticazione:** fuori dallo sviluppo locale usare TLS. Admin e player si autenticano tramite il servizio identità; le API verificano token e permessi (401 senza credenziali valide, 403 senza permesso). Le chiamate monolite-servizio di gioco usano credenziali server-to-server separate; non si riutilizzano sessioni browser. Il pairing/provisioning del device è un'operazione admin autenticata; la credenziale del device resta distinta e viene mostrata solo nella fase autorizzata di provisioning.
- **Versionamento e formato:** prefisso `/api/v1`; JSON UTF-8; date UTC in formato RFC 3339; identificativi di risorsa stabili e opachi per gli altri servizi. Gli errori hanno forma `{ "error": { "code": "...", "message": "...", "correlationId": "..." } }`. Le risposte includono `correlationId`; le chiamate inoltrate propagano lo stesso ID.
- **Timeout e retry:** timeout connessi brevi e configurabili (2 s per connect, 5 s per risposta nel flusso interattivo). Retry limitati con backoff solo su timeout/errore temporaneo e solo per operazioni idempotenti; errori 4xx non si ritentano. Nessun client deve interpretare un timeout di scrittura come prova che l'operazione non sia stata applicata.
- **Comandi ripetibili:** ogni comando mutante ritentabile porta `Idempotency-Key` (UUID generato dal chiamante). Il servizio memorizza chiave, ambito, hash richiesta e risultato nella stessa transazione della modifica; stessa chiave/stesso contenuto restituisce il risultato originale, stessa chiave/contenuto diverso dà 409. La registrazione di un tiro non viene ritentata senza chiave. Le transizioni partita usano la stessa chiave e transizioni ripetute verso lo stato già raggiunto restituiscono lo stato corrente senza effetti collaterali.
- **Flusso iniziale sincrono:** il monolite crea la partita, assegna partecipanti/dispositivo e ne mantiene lo stato generale. All'avvio invia a `darts-game-service` un contesto completo e versionato (match ID, gioco/configurazione, roster ordinato) e porta la partita a `RUNNING` solo dopo l'inizializzazione riuscita. Il device autenticato invia il tiro all'API del monolite, che lo inoltra al servizio di gioco; la risposta del gioco è l'esito autorevole e il monolite aggiorna lo stato generale in caso di checkout. Una dipendenza indisponibile produce errore temporaneo (503/504) e stato non avanzato; non si fa doppia scrittura nei database.
- **Eventi:** MQTT non è prerequisito della prima estrazione. Quando introdotti, eventi in envelope versionato con `eventId` UUID, `eventType`, `eventVersion`, `occurredAt`, `correlationId`, `matchId` e `payload`; outbox transazionale presso il publisher, deduplicazione persistente presso il consumer, retry limitati e coda/registro per messaggi non elaborabili. Le notifiche non sostituiscono le API sincrone per le decisioni che richiedono esito immediato.
- **Compatibilità/rollback:** in ogni fase un solo percorso è autorevole per le scritture di ciascun dato. Il traffico passa al nuovo proprietario solo dopo migrazione e riconciliazione; rollback instrada nuovamente al vecchio percorso usando una finestra di compatibilità esplicitamente mantenuta. Non si fanno dual-write. Le letture possono essere confrontate in shadow mode, senza effetti collaterali.

#### Sequenza e responsabilità del flusso 501

1. L'admin si autentica tramite `identity-service`; il monolite autorizza l'azione, crea la partita e assegna player/dispositivo.
2. L'admin apre la partita e avvia il gioco. Il monolite invia roster e configurazione a `darts-game-service` con una chiave idempotente. Se il gioco non conferma l'inizializzazione, la partita non diventa `RUNNING`.
3. Il simulatore ottiene la propria credenziale tramite pairing amministrativo. Il dispositivo interroga lo stato presso l'API del monolite; la credenziale identifica il device, non un utente.
4. Il device invia settore e moltiplicatore più `Idempotency-Key` all'API del monolite. Questa valida la credenziale del device e inoltra la richiesta al gioco; il client non sceglie player, punteggio o turno.
5. `darts-game-service` valida stato, turno e regole, registra tiro e aggiornamento in una transazione e restituisce lo stato aggiornato. Al checkout il monolite riceve l'esito e aggiorna lo stato generale.

Interfacce principali sotto `/api/v1`, descritte in OpenAPI: gli endpoint `/matches` e `/devices` appartengono all'API del monolite; `PUT /games/darts/matches/{matchId}/context`, `GET /games/darts/matches/{matchId}/state`, `POST /games/darts/matches/{matchId}/throws` e `POST /games/darts/matches/{matchId}/finish` appartengono al servizio di gioco. Transizioni in conflitto danno 409, input invalido 400/422, risorsa assente 404, credenziali 401/403 e dipendenza non disponibile 503/504.

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

### Fase 4 — Introdurre il servizio di identita

- [x] Creare il servizio identità dedicato con account admin/player, database separato e API interna di login, introspezione e logout.
- [x] Spostare credenziali e login all'identity service; leggere i player abilitati tramite API e usare il subject ID nelle partecipazioni.
- [x] Integrare il login del monolite con il servizio identità e validare token e ruolo admin.
- [ ] Distinguere credenziali utente, credenziali server-to-server e token dei dispositivi.
- [ ] Verificare accesso negato senza credenziali valide e isolamento dei resoconti player.

**Risultato:** identità e credenziali sono gestite dall'identity service; il monolite conserva i dati di dominio e applica le autorizzazioni; il servizio di gioco non gestisce il login utente.

### Fase 5 — Affidabilita, dati e rilascio

- [ ] Aggiungere log strutturati con correlation ID e identificativi partita/evento attraverso tutti i servizi.
- [ ] Definire limiti di retry, timeout, circuitazione dove utile e comportamento in caso di dipendenza non disponibile.
- [ ] Se si pubblicano eventi dopo una scrittura DB, definire come evitare perdita o doppia pubblicazione (per esempio outbox e consumer idempotenti).
- [ ] Documentare migrazioni e rollback per ogni servizio, backup/restore e prova di ripristino.
- [ ] Separare pipeline/build, configurazioni e versione distribuibile di ogni applicazione; mantenere versioni compatibili dei contratti.
- [ ] Eseguire il flusso end-to-end e riconciliare dati e risultati prima di spostare traffico definitivo.

**Risultato:** servizi osservabili e recuperabili, con procedura ripetibile di distribuzione e rollback.

### Fase 6 — Consolidare il passaggio al servizio di gioco

- [ ] Spostare traffico per fasi e monitorare errori e coerenza dei dati; stabilire un criterio di rollback.
- [ ] Fare un backup verificato e completare la riconciliazione dei dati di gioco prima della rimozione delle strutture legacy relative ai tiri.
- [ ] Rimuovere la logica legacy di gioco dal monolite solo dopo il passaggio stabile e verificato, mantenendo le tabelle monolitiche per partite, partecipanti e dispositivi.
- [ ] Aggiornare documentazione, istruzioni operative e diagrammi allo stato realmente implementato.

**Risultato:** la gestione amministrativa resta nel monolite, mentre regole e stato delle Freccette sono serviti dal processo autonomo; il monolite non accede al database del servizio di gioco.

### Regole di avanzamento

- Procedere una fase per volta e aggiornare le caselle e lo stato in questo documento al termine di ogni sessione.
- Ogni fase deve lasciare il sistema avviabile e fornire un percorso di rollback finche la migrazione non e consolidata.
- Non effettuare doppie scritture indipendenti come soluzione transitoria senza una strategia di riconciliazione.
- Non eliminare dati legacy finche backup, conteggi e controlli di coerenza non sono stati verificati.
- La prima fase operativa e la Fase 0; il servizio Freccette viene estratto solo dopo aver chiarito proprieta dei dati e flussi.

## Flusso-obiettivo della partita

### Decisioni consolidate

- L'autenticazione server-to-server e i contratti REST sono definiti nella Fase 1 e nello schema OpenAPI. La Fase 2 documenta l'ambiente XAMPP + PhpStorm. L'estrazione operativa in corso è la Fase 3.

1. L'amministratore si autentica tramite `identity-service`; il monolite autorizza l'azione e gestisce partita, roster e dispositivo.
2. Il monolite conserva dati della partita, profili e associazioni; all'avvio invia il contesto a `darts-game-service`.
3. `darts-game-service` inizializza il proprio stato di gioco senza leggere il database del monolite.
4. Il simulatore si autentica con l'API del monolite e invia un tiro.
5. Il monolite inoltra il tiro a `darts-game-service`, che verifica stato, turno e regole, salva il tiro nel proprio DB, aggiorna punteggio e turno e restituisce il nuovo stato.
6. Il monolite aggiorna lo stato generale della partita in base all'esito restituito dal servizio.
7. L'amministratore consulta partecipanti e risultati nel monolite; in futuro il player autenticato consulta soltanto i propri resoconti.

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

