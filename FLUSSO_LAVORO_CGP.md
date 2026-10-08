# Connected Games Platform — flusso di lavoro

Questo file è il riferimento tra le sessioni di sviluppo. Aggiornare la sezione **Stato** e spuntare le attività concluse al termine di ogni sessione.

## Decisioni confermate

- Il server centrale è questo progetto Yii2.
- Il gioco iniziale è **Freccette**.
- Regole iniziali: punteggio ad accumulo, al massimo 2 giocatori, turni alternati, settore `1–20` oppure bull, moltiplicatore `1–3`.
- Il database contiene già le tabelle necessarie: non creare lo schema da zero. Prima di procedere, ispezionare struttura, chiavi e nomi reali delle tabelle.
- Mancano i modelli Yii2 corrispondenti alle tabelle esistenti.
- Gli amministratori usano l'interfaccia web Yii2 e sono gli unici ad autenticarsi sul server.
- I player non effettuano login nel server: interagiscono soltanto mediante le applicazioni esterne.
- L'OpenAPI verrà definito quando inizierà la gestione delle partite.
- Il simulatore della freccettiera e il client utente saranno applicazioni esterne; il server è l'unico componente con accesso diretto al database.

## Stato iniziale

- [x] Yii2 Basic Application installata e avviabile.
- [x] Connessione database configurata in `config/db.php`.
- [x] Struttura standard dell'applicazione presente.
- [x] Errore sintattico iniziale in `models/LoginForm.php` corretto.
- [x] Struttura effettiva del database verificata.
- [x] Modelli ActiveRecord creati.
- [x] Gestione amministrativa delle partite implementata.
- [ ] API per simulatore e client esterni implementate.

## Ordine di lavoro

### Fase 1 — ricognizione del database

Obiettivo: allineare Yii2 alle tabelle già esistenti, senza modificarle involontariamente.

- [x] Leggere le tabelle, le colonne, gli indici, le chiavi esterne e i dati iniziali.
- [x] Confrontare lo schema reale con il dominio atteso: utenti/amministratori, giochi, partite, partecipanti e tiri.
- [x] Decidere eventuali adattamenti minimi allo schema solo dopo averli concordati.
- [x] Annotare qui nomi effettivi di tabelle e relazioni.

**Esito atteso:** una mappa affidabile database → modello Yii2.

#### Esito della ricognizione (2026-09-23)

| Tabella reale | Modello Yii2 previsto | Colonne e relazioni rilevanti |
| --- | --- | --- |
| `tbl_user` | `User` | `username` univoco; `password_hash`, `auth_key`, `role` (predefinito `player`). |
| `tbl_game` | `Game` | `code` univoco; contiene il gioco iniziale `Freccette` (`id=1`, `code=darts`). |
| `tbl_matches` | `Match` | FK `game_id → tbl_game.id`; stato numerico 0–3, massimo 2 player predefinito. |
| `tbl_match_players` | `MatchPlayer` | FK `match_id → tbl_matches.id`, `user_id → tbl_user.id`; vincolo univoco `(match_id, user_id)`, punteggio e ordine turno. |
| `tbl_throws` | `Throw` | FK `match_id → tbl_matches.id`, `user_id → tbl_user.id`; settore, moltiplicatore, punteggio e data. |

Gli indici coprono tutte le chiavi esterne e i vincoli necessari al dominio. I dati iniziali contengono solo il gioco Freccette; non sono presenti utenti (quindi nemmeno amministratori), partite, partecipanti o tiri.

Non sono richiesti adattamenti allo schema in questa fase: i nomi fisici `tbl_*` saranno dichiarati esplicitamente nei modelli ActiveRecord. Lo stato di `tbl_matches.status` coincide con `MatchStatus` (`0=CREATED`, `1=OPEN`, `2=RUNNING`, `3=FINISHED`).

Nota per la Fase 2: `models/Game.php` punta ancora a `{{%game}}` e `models/User.php` usa utenti in memoria; entrambi dovranno essere sostituiti/allineati alle tabelle sopra senza modificare il database.

### Fase 2 — modelli Yii2 e relazioni

Obiettivo: rendere disponibili i dati tramite ActiveRecord e relazioni chiare.

- [x] Creare o generare i modelli per tutte le tabelle esistenti.
- [x] Definire regole di validazione e relazioni (`partita → gioco`, `partecipante → partita/utente`, `tiro → partita/giocatore`).
- [x] Identificare il modello dell'amministratore e il meccanismo di password hash.
- [x] Verificare lettura e scrittura controllata su un database di sviluppo.

**Esito atteso:** i dati possono essere caricati e salvati da Yii2 senza SQL sparso nei controller.

#### Esito dell'implementazione (2026-09-23)

- Modelli ActiveRecord: `User`, `Game`, `GameMatch`, `MatchPlayer` e `DartThrow`. I prefissi `Game` e `Dart` evitano le parole riservate PHP `match` e `throw`.
- Relazioni: `Game → matches`, `GameMatch → matchPlayers`, `GameMatch → throws`, `MatchPlayer → user/gameMatch/throws`. Poiché `tbl_throws` non contiene `match_player_id`, la relazione logica `MatchPlayer → throws` usa la coppia `match_id` e `user_id`.
- `User` è l'unico modello identità per player e futuri amministratori. La password è conservata esclusivamente in `password_hash` e viene verificata tramite `Yii::$app->security`; i permessi amministrativi saranno introdotti nella Fase 3, non nei modelli.
- Le validazioni proteggono chiavi esterne, unicità di username e partecipazione, stati 0–3, massimo due player, punteggi non negativi e parametri di tiro. Per ora il bull è rappresentato dal settore `25`; le regole di punteggio definitive restano di competenza della Fase 5.
- Verifica eseguita in una transazione MySQL: inseriti utente, partita, partecipante e tiro, ricaricate tutte le relazioni, quindi effettuato rollback. Nessun dato di prova è rimasto nel database.

### Fase 3 — accesso amministratore

Obiettivo: proteggere l'area web di amministrazione.

- [x] Collegare il login Yii2 agli amministratori presenti nel database.
- [x] Limitare dashboard e pagine di gestione agli amministratori autenticati.
- [x] Definire logout e gestione delle sessioni.
- [x] Escludere esplicitamente i player dal login web/server.

**Esito atteso:** un amministratore può entrare nell'area riservata; un player non può farlo.

#### Esito dell'implementazione (2026-09-23)

- L'URL iniziale visualizza il form di accesso; dopo un login valido l'utente viene reindirizzato a `dashboard/index`, attualmente intenzionalmente vuota.
- Il login legge `tbl_user`, verifica `password_hash` mediante `Yii::$app->security` e consente l'accesso solo a utenti con `role = admin`, senza creare un modello amministratore separato.
- Dashboard e logout sono protetti dalla sessione Yii2; la dashboard verifica anche esplicitamente il ruolo amministrativo. Il logout invalida la sessione e riporta al form di accesso.
- I player sono respinti dal login web. Il database non contiene ancora un amministratore: prima dell'uso effettivo occorre creare un utente con password hash valida e ruolo `admin`.
- Per creare il primo amministratore in modo locale e interattivo: `php yii admin/create <username>`. Il comando non accetta password come argomento, richiede password e conferma nascoste, rifiuta duplicati e richiede conferma prima del salvataggio.
- Per creare un player in modo locale e interattivo: `php yii player/create <username>`. Il comando applica gli stessi controlli e salva il ruolo `player`; il player non può autenticarsi nell'interfaccia web amministrativa.

### Fase 4 — gestione web delle partite

Obiettivo: rendere possibile il ciclo di vita di una partita dal pannello admin.

- [x] Dashboard con giochi supportati e partite recenti/attive.
- [x] Vista Freccette con elenco delle partite.
- [x] Creazione partita: nome, gioco, massimo giocatori (massimo 2) e configurazione essenziale.
- [x] Transizioni controllate: `CREATED → OPEN → RUNNING → FINISHED`.
- [x] Vista dettaglio: partecipanti, punteggi, tiri e stato.
- [x] Azioni amministratore: aprire, avviare e terminare una partita.

**Esito atteso:** l'amministratore gestisce una partita completa dall'interfaccia web.

#### Avanzamento e completamento (2026-10-08)

- La dashboard mostra i giochi abilitati, le partite attive (create, aperte o in corso) e le otto partite più recenti.
- La sidebar contiene la sezione espandibile **Partite freccette**, con collegamenti a elenco e creazione.
- L'elenco mostra soltanto le partite del gioco con codice `darts`, tramite GridView Yii2 con filtri, ordinamento, paginazione e aggiornamento Pjax. La creazione richiede nome e numero massimo di giocatori (1 o 2), associa automaticamente Freccette e salva nello stato `CREATED`.
- `DartsMatch` estende `GameMatch` e raccoglie il comportamento specifico delle Freccette, incluso l'elenco filtrabile; le configurazioni e le regole del gioco saranno aggiunte allo stesso modello o a servizi dedicati nelle fasi successive.
- La colonna **Dettagli** e il nome della partita aprono una pagina dedicata; la modifica di nome e massimo giocatori resta separata e preserva gioco e stato.
- Il dettaglio mostra stato e date del ciclo di vita, partecipanti con punteggio e tiri registrati. Le sezioni sono utilizzabili anche quando non ci sono ancora partecipanti o tiri.
- L'amministratore può aprire, avviare e terminare la partita mediante richieste POST protette. Da `OPEN` può avviarla oppure terminarla manualmente; da `RUNNING` può terminarla. Il server controlla le transizioni e registra `started_at`/`ended_at`. L'avvio non richiede ancora partecipanti: l'iscrizione sarà introdotta con le API della Fase 6.

### Fase 5 — logica di gioco freccette

Obiettivo: centralizzare e validare le regole nel server.

- [ ] Creare un servizio applicativo per la partita e un servizio per il calcolo del tiro.
- [ ] Validare settore, bull e moltiplicatore.
- [ ] Accettare tiri solo per partite `RUNNING` e per il giocatore di turno.
- [ ] Salvare ogni tiro e aggiornare il punteggio del partecipante nella stessa operazione logica.
- [ ] Passare il turno al giocatore successivo.
- [ ] Stabilire e implementare la condizione di termine secondo le regole concordate.

**Esito atteso:** nessun client calcola o modifica direttamente il punteggio.

### Fase 6 — API per componenti esterni

Obiettivo: esporre REST/JSON per simulatore e client player, senza login player sul server.

- [ ] Definire come identificare/abilitare il simulatore (ad esempio chiave del dispositivo o partita selezionata).
- [ ] Esporre endpoint per lettura partite pubbliche/aperte e relativo stato.
- [ ] Esporre endpoint per join/uscita del player, coerente con l'identificazione usata dalle app esterne.
- [ ] Esporre endpoint protetto per inviare un tiro del simulatore.
- [ ] Restituire JSON coerente, codici HTTP adeguati ed errori leggibili.
- [ ] Redigere `documentation/openapi.yaml` con endpoint e payload effettivamente scelti.

**Esito atteso:** un client esterno può ottenere lo stato; il simulatore può registrare un tiro; il DB rimane accessibile solo al server.

### Fase 7 — applicazioni esterne

Obiettivo: dimostrare la separazione client/server.

- [ ] Creare il simulatore Java CLI: scelta partita, giocatore, settore e moltiplicatore; invio REST; stampa risposta.
- [ ] Creare il client Java: elenco partite, join/uscita e visualizzazione di stato/punteggi.
- [ ] Verificare che nessuna delle due applicazioni usi credenziali o connessioni del database.

**Esito atteso:** il flusso completo funziona da processi separati.

### Fase 8 — verifica e documentazione finale

- [ ] Testare il flusso: admin crea → apre → player entra da app esterna → admin avvia → simulatore invia tiri → client legge punteggi → admin termina.
- [ ] Documentare installazione, configurazione DB e avvio del server.
- [ ] Aggiornare OpenAPI, README e diagrammi: use case, classi, sequenza di registrazione tiro e architettura.
- [ ] Valutare MQTT, Keycloak e ulteriori giochi solo dopo il completamento del flusso minimo.

## Estensione schema (2026-10-03)

- Aggiunta mediante migration Yii la tabella `sys_autoconfigurazione`: `id` autoincrementale, `nome` univoco (`varchar(255)`), `valore` e `descrizione` (`LONGTEXT`, nullable).
- Il modello `Autoconfigurazione` espone gli accessor tipizzati stringa, booleano, intero, float e array. Se una chiave manca, viene creata con il valore predefinito ricevuto; se esiste, il valore nel database ha sempre precedenza.
- La pagina amministrativa `gestore_temi` salva in autoconfigurazione il colore principale diurno (`tema.colore_diurno`) e notturno (`tema.colore_notturno`), applicandolo a sidebar e pulsanti primari.

## Promemoria per ogni sessione

1. Leggere questo file e controllare la sezione **Stato**.
2. Lavorare su una sola attività o sottofase alla volta.
3. Verificare il risultato prima di spuntare l'attività.
4. Aggiornare qui decisioni, blocchi o variazioni di schema.
5. Non introdurre MQTT, OAuth/Keycloak o altri giochi prima della Fase 8.

## Note e decisioni da completare

- Definire l'identità dei player nelle app esterne: utente preesistente, nickname temporaneo o altro identificativo.
- Definire l'autorizzazione del simulatore: chiave dispositivo, token tecnico o altra soluzione semplice.
- Definire la condizione precisa di fine partita nel punteggio ad accumulo.
- Riportare qui le tabelle effettive dopo la ricognizione della Fase 1.
