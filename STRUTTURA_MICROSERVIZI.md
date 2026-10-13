# Pianificazione futura dell'architettura a microservizi

Questo documento descrive una possibile evoluzione futura della Connected Games Platform. **Non e la descrizione dell'architettura attualmente implementata.** L'obiettivo e separare l'applicazione Yii2 esistente in servizi autonomi, distribuibili e avviabili indipendentemente, ciascuno proprietario dei propri dati.

## Stato attuale

Il repository contiene un'unica applicazione Yii2 e un solo servizio PHP in `docker-compose.yml`. La gestione web, il controller API e i servizi applicativi condividono il progetto e la configurazione. Sono gia presenti un'API per il dispositivo Freccette (`DartsApiController`) e la logica di gioco (`DartsGameService`, `DartsThrowCalculator`), ma **non sono microservizi separati**.

La configurazione di piu connessioni DB nella stessa app Yii2 non sarebbe sufficiente: per questa pianificazione, ogni servizio dovra essere un'applicazione/processo autonomo, con API e ciclo di rilascio indipendenti.

## Servizi proposti

La proposta comprende tre servizi applicativi sviluppati nel progetto e un servizio esterno per l'identita.

| Servizio | Responsabilita | Dati di proprieta |
| --- | --- | --- |
| **Gestione partite (`matches-service`)** | API e interfaccia amministrativa; creazione, apertura, avvio e chiusura delle partite; gestione dei partecipanti; associazione del dispositivo. | Partite, configurazione e partecipanti. Conserva gli identificativi esterni di utenti e dispositivi, senza relazioni SQL verso i loro database. |
| **Dispositivi e IoT (`devices-service`)** | Registrazione, abilitazione e pairing dei dispositivi; autenticazione delle richieste provenienti da simulatore o dispositivo fisico. | Dispositivi e credenziali/token. I segreti non vengono restituiti dopo la creazione o il pairing previsto. |
| **Gioco Freccette (`darts-game-service`)** | Validazione dei tiri, regole 501, gestione dei turni, punteggi, bust e checkout; esposizione dello stato di gioco. | Tiri e stato di gioco necessario a ricostruire o consultare punteggio e turno. |
| **Identita e accessi (Keycloak)** | Autenticazione, ruoli e rilascio di token JWT per amministratori, player e client autorizzati. | Archivio gestito da Keycloak nel suo database dedicato. Gli altri servizi usano l'identificativo stabile dell'utente (`sub`) e i claim autorizzativi del token. |

Keycloak e proposto come componente autonomo gia disponibile, non come un servizio da riscrivere in Yii2. Un eventuale gateway API non e incluso nella prima fase: puo essere valutato successivamente se serve un unico indirizzo pubblico o un punto centralizzato per routing e limiti di traffico.

## Proprieta e separazione dei database

Ogni servizio applicativo accede soltanto al proprio database. In ambiente di sviluppo i database possono risiedere sulla stessa istanza MySQL, ma devono avere schema/database distinti e credenziali con privilegi limitati. Keycloak usa il proprio database, per esempio PostgreSQL.

- `matches-service` e l'unico a leggere e modificare il database delle partite.
- `devices-service` e l'unico a leggere e modificare il database dei dispositivi.
- `darts-game-service` e l'unico a leggere e modificare il database di gioco.
- Keycloak e l'unico a gestire il proprio archivio di identita.
- Non si creano foreign key tra database appartenenti a servizi diversi. Si usano identificativi opachi e API/eventi.
- Nessun servizio condivide credenziali DB con un altro servizio.

La separazione implica che non esista una singola transazione SQL attraverso servizi e database diversi. Ogni servizio garantisce la coerenza dei propri dati; gli altri si aggiornano tramite chiamate o eventi e devono gestire ritardi, errori e ripetizioni.

## Comunicazione prevista

- **REST/JSON su HTTP** per comandi e interrogazioni che richiedono una risposta immediata, ad esempio creare una partita, verificare un dispositivo o leggere lo stato di gioco.
- **MQTT publish/subscribe** per eventi asincroni e aggiornamenti che interessano piu componenti. Il broker e infrastruttura condivisa, non un microservizio di dominio.
- **JWT di Keycloak** per autenticare gli utenti. Per i dispositivi si puo usare un token dedicato verificato da `devices-service`; non si riutilizzano token utente.

Eventi iniziali da definire e versionare:

- `MatchCreated`, `PlayersAssigned`, `MatchStarted` e `MatchEnded`, pubblicati da `matches-service`.
- `ThrowRegistered` e `GameFinished`, pubblicati da `darts-game-service`.
- Gli eventi devono includere almeno un identificativo evento, il tipo/versione, l'identificativo partita e la data; il consumatore deve poter riconoscere duplicati.

Il servizio di gioco deve ricevere il contesto necessario per iniziare (partita, partecipanti, ordine di turno e configurazione), senza leggere direttamente il DB partite. Il dispositivo invia i dati grezzi del tiro; e il servizio di gioco a determinare turno, punteggio ed esito. Il servizio dispositivi autentica il chiamante e il flusso inoltra il comando al servizio di gioco. Il dettaglio dei flussi e delle responsabilita API andra specificato prima dell'estrazione.

## Esecuzione locale

Un unico `docker compose up --build` dalla root dovra avviare i processi distinti e le rispettive dipendenze. La root del repository puo restare un **monorepo**: non e necessario dividere subito il codice in repository Git diversi.

Struttura obiettivo indicativa:

```text
connected_games_platform/
  services/
    matches-service/       # app Yii2, API/admin e Dockerfile
    devices-service/       # app Yii2, API e Dockerfile
    darts-game-service/    # app Yii2, API e Dockerfile
  infrastructure/
    keycloak/              # realm/configurazione di sviluppo, senza segreti reali
    mqtt/                  # configurazione del broker
  docker-compose.yml
```

Servizi/container previsti:

| Container Compose | Porta host indicativa | Persistenza |
| --- | ---: | --- |
| `matches-service` | `8001` |  |
| `matches-db` | non esposta per default | volume dedicato |
| `devices-service` | `8002` |  |
| `devices-db` | non esposta per default | volume dedicato |
| `darts-game-service` | `8003` |  |
| `darts-game-db` | non esposta per default | volume dedicato |
| `keycloak` | `8080` |  |
| `keycloak-db` | non esposta per default | volume dedicato |
| `mqtt-broker` | `1883` (sviluppo) | configurazione/volume secondo necessita |

Le porte host sono indicative e si possono cambiare. Tra container si usano i nomi DNS di Compose, per esempio `http://darts-game-service`; `localhost` dentro un container indica quel container stesso. Le password e i secret non vanno inseriti nel file Compose versionato: usare variabili locali/secrets e fornire un file di esempio senza credenziali reali. Aggiungere healthcheck e tentativi di riconnessione: `depends_on` da solo non garantisce che una dipendenza sia pronta a ricevere richieste.

Comandi di sviluppo previsti:

```powershell
# Avvia tutti i componenti
docker compose up --build

# Avvia in background
docker compose up --build -d

# Log dei servizi
docker compose logs -f matches-service devices-service darts-game-service

# Arresta i container; i volumi persistenti restano
docker compose down
```

## Migrazione dal monolite

La migrazione si svolge in questo repository (monorepo), mantenendo inizialmente l'applicazione Yii2 attuale come riferimento e percorso funzionante. Ogni servizio estratto sara una propria applicazione Yii2, con entry point, configurazione, dipendenze, processo, migrazioni e database di proprieta. Non si inizia copiando tutto il progetto tre volte: si estrae una responsabilita per volta e si sostituiscono i collegamenti interni con contratti espliciti.

Le fasi sono sequenziali. Una fase si considera conclusa solo quando i suoi risultati sono verificabili e il flusso esistente continua a funzionare. Gli elementi `[ ]` sono caselle da spuntare durante il lavoro.

### Fase 0 — Rilievo e confini dei servizi

- [ ] Inventariare tabelle, modelli, controller, comandi e flussi che scrivono o leggono ciascun dato.
- [ ] Assegnare ogni tabella a un solo proprietario e registrare gli identificativi che gli altri servizi possono conservare.
- [ ] Definire la fonte autorevole per stato, partecipanti, punteggi, dispositivo e identita utente.
- [ ] Segnare relazioni ActiveRecord e foreign key che attraversano i confini previsti; pianificare come sostituirle.
- [ ] Confermare che la prima dimostrazione copra una partita 501 completa, dal pairing dispositivo al checkout.

**Risultato:** mappa dati/flussi e confini approvati prima di spostare codice. In particolare va risolta la decisione su chi possiede partecipanti e punteggio corrente, oggi usati sia dalla gestione partita sia dal gioco.

### Fase 1 — Contratti e flussi

- [ ] Disegnare le sequenze di creazione/apertura/avvio partita, pairing, tiro e chiusura.
- [ ] Definire API REST con richieste, risposte, codici errore, autenticazione, timeout e versionamento; documentarle in OpenAPI.
- [ ] Definire identificativi stabili e payload/versioni degli eventi MQTT, oltre a deduplicazione e gestione dei messaggi non elaborabili.
- [ ] Specificare idempotenza dei comandi che possono essere ritentati, soprattutto registrazione tiro e transizioni partita.
- [ ] Definire le regole di compatibilita durante la migrazione: chi instrada ogni richiesta e come si evita la doppia scrittura.

**Risultato:** contratti consultabili e un flusso end-to-end documentato. Usare HTTP per risposte immediate e introdurre MQTT solo per eventi che devono essere distribuiti in modo asincrono.

### Fase 2 — Rendere riproducibile l'ambiente locale

- [ ] Aggiungere una struttura `services/` nel repository per applicazioni Yii2 autonome e `infrastructure/` per le dipendenze condivise.
- [ ] Preparare configurazioni Docker e Compose per il monolite attuale, senza interromperne l'avvio.
- [ ] Introdurre configurazione tramite ambiente e file di esempio; tenere fuori dal repository password e segreti reali.
- [ ] Predisporre database/schema e credenziali separate per ogni servizio, inizialmente anche sulla stessa istanza MySQL.
- [ ] Aggiungere broker MQTT solo quando i contratti della fase 1 lo richiedono, oltre a healthcheck e persistenza locale.

**Risultato:** avvio documentato da una macchina pulita; ogni componente ha configurazione isolata e readiness verificabile.

### Fase 3 — Estrarre `darts-game-service`

- [ ] Creare l'applicazione Yii2 autonoma del gioco con endpoint di avvio/inizializzazione, stato e registrazione tiro.
- [ ] Trasferire calcolatore, regole 501, tiri e stato di gioco nel servizio e nelle sue migrazioni.
- [ ] Fare ricevere al gioco il contesto necessario (ID partita, partecipanti, ordine e configurazione) attraverso il contratto definito, mai dal DB partite.
- [ ] Rendere la registrazione del tiro atomica nel DB del gioco e idempotente verso retry.
- [ ] Sostituire una chiamata interna alla logica di gioco con HTTP dal sistema esistente; mantenere il vecchio percorso disponibile per rollback durante la verifica.
- [ ] Verificare partite nuove e, se richiesto, migrare partite in corso/storiche con conteggi e riconciliazione dei punteggi.

**Risultato:** il gioco si avvia e si distribuisce da solo, possiede i propri dati e completa una partita senza accesso al database monolitico.

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

- [ ] Configurare Keycloak in ambiente di sviluppo con realm, ruoli e client documentati e importabili.
- [ ] Definire mapping degli utenti Yii2 a identificativi `sub`; preparare migrazione e recupero accesso senza esporre password.
- [ ] Fare validare issuer, firma, audience e ruoli dei JWT da ciascun servizio.
- [ ] Applicare autorizzazioni agli endpoint e distinguere token utente da credenziali device e chiamate tra servizi.
- [ ] Verificare rinnovo/scadenza token e rifiuto di ruoli o audience non validi.

**Risultato:** autenticazione e autorizzazione funzionano nel flusso completo senza dipendere dal login locale legacy.

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

1. L'amministratore autenticato con Keycloak crea la partita in `matches-service` e assegna player e dispositivo.
2. `matches-service` verifica l'abilitazione del dispositivo tramite API di `devices-service` e pubblica gli eventi di assegnazione/avvio.
3. `darts-game-service` riceve il contesto della partita e prepara il proprio stato di gioco.
4. Il simulatore si autentica con `devices-service` e invia un tiro.
5. Il tiro arriva a `darts-game-service`, che verifica stato, turno e regole, salva il tiro nel proprio DB, aggiorna punteggio e turno e restituisce il nuovo stato.
6. `darts-game-service` pubblica l'evento di tiro/conclusione. I servizi interessati aggiornano la propria vista o notificano i client.
7. Il player consulta le partite e i punteggi attraverso API autorizzate, senza accesso ai database interni.

## Ambito iniziale e criteri di completamento

Per contenere la complessita, la prima dimostrazione dovrebbe includere il flusso minimo: autenticazione, creazione e avvio partita, pairing dispositivo, tiro valido/non valido, aggiornamento del punteggio e conclusione. Non occorre implementare subito classifiche, notifiche push o gateway.

La separazione puo dirsi realizzata quando:

- ogni servizio applicativo si avvia come processo/container distinto e puo essere aggiornato senza ricostruire gli altri;
- ogni servizio possiede un DB/schema e credenziali dedicati e nessun servizio accede direttamente ai DB altrui;
- le interazioni tra servizi passano da contratti HTTP o messaggi documentati;
- il flusso end-to-end funziona avviando l'ambiente Compose;
- migrations e backup sono gestiti separatamente per ogni database.

## Riferimenti al materiale del corso

- `D:\Universita\Vercelli\PISSIR\laboratorio\2 The MicroServices architecture style.pdf`: servizi autonomi/eseguibili, API, decomposizione e comunicazione tra componenti.
- `D:\Universita\Vercelli\PISSIR\laboratorio\3 - REST HTTP Servers- nozioni di base.pdf`: risorse, URI, metodi HTTP e rappresentazioni JSON.
- `D:\Universita\Vercelli\PISSIR\laboratorio\5 - Modello Publish_Subscribe- introduzione e componenti.pdf`, `6 - Mosquitto- uso e configurazione.pdf` e `9 Arduino wi-fi e MQTT.pdf`: publish/subscribe e MQTT per la comunicazione IoT.
- `D:\Universita\Vercelli\PISSIR\laboratorio\7 Oauth2 e Keycloak.pdf`: authorization server, OAuth 2.0, Keycloak e JWT.

