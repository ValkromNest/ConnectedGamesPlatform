# Connected Games Platform — Analisi finale e piano di lavoro

## 1. Obiettivo del progetto

Realizzare una versione ridotta ma coerente della **Connected Games Platform**, concentrandosi inizialmente su un solo gioco: **freccette**.

L'implementazione deve dimostrare soprattutto:

- presenza di una piattaforma centrale;
- separazione tra server e client;
- uso di API REST e JSON;
- gestione di utenti, partite e punteggi;
- simulazione software dei dispositivi/sensori;
- accesso al database esclusivamente tramite il server centrale.

Non è necessario implementare da subito tutte le funzionalità previste dalla bozza completa del progetto.

---

## 2. Architettura proposta

La struttura consigliata è:

```text
                    CONNECTED GAMES PLATFORM
                         Server PHP
                             |
              +--------------+--------------+
              |              |              |
            Auth           Games          Matches
                                             |
                                           Throws
                             |
                             v
                       MySQL / MariaDB


          USER APPLICATION            SENSOR SIMULATOR
          Java / Desktop / Web          Java CLI
                 |                          |
                 | REST / JSON              | REST / JSON
                 +------------+-------------+
                              |
                              v
                    Connected Games Platform
```

In una versione successiva il simulatore del sensore potrà comunicare tramite MQTT.

---

## 3. Componenti da realizzare

### 3.1 Server centrale

Nome del progetto:

**Connected Games Platform**

Responsabilità:

- autenticazione degli amministratori;
- autenticazione degli utenti/client;
- gestione dei giochi supportati;
- gestione delle partite;
- gestione dei partecipanti;
- registrazione dei tiri;
- calcolo del punteggio;
- salvataggio dei dati;
- esposizione delle API REST;
- interfaccia web per gli amministratori.

Il server deve essere l'unico componente che accede direttamente al database.

---

### 3.2 Interfaccia amministratore

Il server deve fornire una piccola interfaccia web.

Funzioni iniziali:

```text
Login amministratore

Dashboard
|
+-- Giochi supportati
|   |
|   +-- Freccette
|
+-- Partite
|
+-- Utenti
```

Per la sezione Freccette:

```text
Freccette

Partite attive

#21    Mario vs Luca       In corso
#22    Partita Test        In attesa

[ Nuova partita ]
```

L'amministratore deve poter:

- creare una partita;
- impostare il numero massimo di giocatori;
- impostare eventualmente la modalità di gioco;
- aprire la partita agli utenti;
- avviare la partita;
- terminare la partita;
- visualizzare punteggi e stato.

---

## 4. Applicazione utente

L'applicazione deve essere separata dal server.

Per semplicità può essere:

- applicazione Java desktop;
- applicazione Java CLI;
- piccola applicazione web separata;
- app mobile in una fase successiva.

Per la prima versione è consigliabile una **applicazione Java desktop o CLI**.

Funzioni:

```text
Login
|
+-- Visualizza partite disponibili
|
+-- Partecipa a una partita
|
+-- Visualizza partita
|
+-- Visualizza punteggio
|
+-- Esci dalla partita
```

Esempio:

```text
Connected Games Client

Username: ________
Password: ________

[ Login ]
```

Dopo il login:

```text
Partite disponibili

#21 Freccette    1/2 giocatori
#22 Freccette    0/2 giocatori

[ Partecipa ]
```

---

## 5. Simulatore della freccettiera

Conviene separare il concetto di **utente** dal concetto di **sensore**.

L'app utente rappresenta la persona.

Il simulatore rappresenta la freccettiera fisica.

Questo rende il progetto più vicino a un vero sistema IoT.

Esempio:

```text
=== DARTBOARD SIMULATOR ===

Partita: #21

1 - Tiro singolo
2 - Tiro doppio
3 - Tiro triplo
4 - Bull
5 - Esci

Settore:
> 20
```

Il simulatore genera un evento:

```json
{
  "matchId": 21,
  "playerId": 4,
  "sector": 20,
  "multiplier": 3
}
```

Il server calcola:

```text
20 * 3 = 60 punti
```

e aggiorna la partita.

---

## 6. Comunicazione tra componenti

Prima versione:

```text
User App
   |
   | REST / JSON
   v
Server PHP
   |
   v
Database
```

e:

```text
Dartboard Simulator
   |
   | REST / JSON
   v
Server PHP
```

Versione successiva opzionale:

```text
Dartboard Simulator
        |
        | MQTT
        v
     Mosquitto
        |
        v
   Edge / Server
        |
        | REST
        v
Connected Games Platform
```

MQTT non è necessario nella prima implementazione.

---

## 7. API REST minime

### Autenticazione

```text
POST /api/login
```

Esempio richiesta:

```json
{
  "username": "mario",
  "password": "password"
}
```

Risposta:

```json
{
  "token": "ABC123"
}
```

Le richieste successive useranno:

```text
Authorization: Bearer ABC123
```

### Giochi

```text
GET /api/games
GET /api/games/{id}
```

Per ora esisterà solo:

```text
Freccette
```

### Partite

```text
GET  /api/matches
GET  /api/matches/{id}

POST /api/matches

POST /api/matches/{id}/join
POST /api/matches/{id}/start
POST /api/matches/{id}/end
```

### Tiri

```text
POST /api/matches/{id}/throws
```

Payload:

```json
{
  "playerId": 4,
  "sector": 20,
  "multiplier": 3
}
```

Risposta:

```json
{
  "score": 60,
  "totalScore": 180
}
```

---

## 8. Stati della partita

Usare pochi stati semplici:

```text
0 = CREATED
1 = OPEN
2 = RUNNING
3 = FINISHED
```

Flusso:

```text
Admin crea partita
        |
        v
     CREATED
        |
        v
Admin apre la partita
        |
        v
       OPEN
        |
        v
Utenti partecipano
        |
        v
Admin avvia
        |
        v
     RUNNING
        |
        v
Partita conclusa
        |
        v
     FINISHED
```

---

## 9. Database minimo

### users

```text
id
username
password_hash
role
```

Possibili ruoli:

```text
ADMIN
PLAYER
```

### games

```text
id
name
code
enabled
```

Esempio:

```text
1
Freccette
DARTS
1
```

### matches

```text
id
game_id
name
status
max_players
created_at
started_at
ended_at
```

### match_players

```text
id
match_id
user_id
score
turn_order
```

### throws

```text
id
match_id
user_id
sector
multiplier
score
created_at
```

---

## 10. PHP puro, Slim o Yii2

### PHP puro

Possibile per un progetto piccolo.

Richiede però di implementare manualmente:

- routing;
- parsing JSON;
- validazione;
- autenticazione;
- gestione degli errori;
- codici HTTP;
- gestione dei token;
- accesso al database;
- serializzazione JSON.

Va bene se si vuole mostrare nel dettaglio il funzionamento di un server HTTP/REST, ma aumenta rapidamente il lavoro.

### Slim

Buon compromesso.

Permette di mantenere il progetto piccolo senza dover scrivere un router da zero.

Esempio concettuale:

```php
$app->get('/api/matches', ...);
$app->post('/api/matches', ...);
$app->post('/api/matches/{id}/join', ...);
```

La logica applicativa può comunque essere organizzata manualmente.

Struttura:

```text
server/
|
+-- public/
|   +-- index.php
|
+-- src/
|   +-- Controller/
|   +-- Service/
|   +-- Repository/
|   +-- Database.php
|
+-- config/
```

### Yii2

È la scelta più veloce se si vuole sfruttare esperienza già acquisita.

Fornisce già:

- routing;
- controller;
- ActiveRecord;
- validazione;
- autenticazione;
- gestione JSON;
- gestione errori;
- connessione DB.

Per questo progetto non è necessario usare funzionalità complesse di Yii2.

Si può mantenere l'applicazione molto semplice.

---

## 11. Scelta consigliata

Le due alternative principali sono:

### Soluzione A — più didattica e minimale

```text
Server: PHP + Slim
Client: Java
Simulatore: Java CLI
DB: MySQL / MariaDB
```

Vantaggi:

- server semplice;
- REST molto visibile;
- poca “magia” del framework;
- buona aderenza agli argomenti del corso.

### Soluzione B — più veloce da sviluppare

```text
Server: Yii2
Client: Java
Simulatore: Java CLI
DB: MySQL / MariaDB
```

Vantaggi:

- sviluppo più rapido;
- minore lavoro infrastrutturale;
- più tempo da dedicare alla logica del progetto.

---

## 12. Autenticazione

Per la prima versione non è necessario implementare OAuth2 o Keycloak.

È sufficiente:

```text
username
password
token applicativo
```

Flusso:

```text
Client
  |
  | POST /api/login
  v
Server
  |
  | verifica credenziali
  v
Token
```

Poi:

```text
Authorization: Bearer TOKEN
```

Keycloak può essere indicato come possibile evoluzione futura.

---

## 13. Regole delle freccette

Per evitare che il progetto diventi troppo complesso, inizialmente usare una sola modalità.

Possibili alternative:

```text
Modalità semplificata
```

oppure:

```text
301
```

La prima versione può limitarsi a:

- 2 giocatori;
- punteggio iniziale;
- alternanza dei turni;
- registrazione del tiro;
- sottrazione/aggiunta dei punti;
- conclusione della partita.

Non è necessario implementare immediatamente tutte le regole ufficiali delle freccette.

---

## 14. Documentazione da produrre

Preparare:

### Use Case Diagram

Casi d'uso principali:

```text
UC01 - Login
UC02 - Visualizzare giochi
UC03 - Creare partita
UC04 - Visualizzare partite disponibili
UC05 - Partecipare a una partita
UC06 - Avviare partita
UC07 - Registrare tiro
UC08 - Visualizzare stato partita
UC09 - Terminare partita
```

### Class Diagram

Classi principali:

```text
User
Game
Match
MatchPlayer
Throw
```

### Sequence Diagram

Almeno:

```text
Login
Join match
Register throw
Start match
End match
```

Esempio registrazione tiro:

```text
Simulator        REST API        MatchService        DB
    |                |                |              |
    | POST throw     |                |              |
    |--------------->|                |              |
    |                | registerThrow  |              |
    |                |--------------->|              |
    |                |                | INSERT       |
    |                |                |------------->|
    |                |                |              |
    |                |                | update score |
    |                |                |------------->|
    |                |<---------------|              |
    | JSON response  |                |              |
    |<---------------|                |              |
```

---

## 15. OpenAPI / Swagger

Creare un file:

```text
openapi.yaml
```

che documenti almeno:

```text
POST /api/login

GET /api/games

GET /api/matches
POST /api/matches

GET /api/matches/{id}

POST /api/matches/{id}/join
POST /api/matches/{id}/start
POST /api/matches/{id}/throws
POST /api/matches/{id}/end
```

---

## 16. Struttura del repository

```text
connected-games-platform/
|
+-- documentation/
|   +-- use-cases/
|   +-- class-diagram/
|   +-- sequence-diagrams/
|   +-- architecture/
|   +-- openapi.yaml
|
+-- server/
|   +-- PHP / Slim oppure Yii2
|
+-- user-client/
|   +-- Java
|
+-- dartboard-simulator/
|   +-- Java
|
+-- database/
|   +-- schema.sql
|
+-- README.md
```

---

## 17. Fasi di sviluppo consigliate

### Fase 1 — progettazione

Definire:

- architettura;
- componenti;
- database;
- API REST;
- casi d'uso;
- diagrammi UML.

### Fase 2 — server base

Implementare:

- connessione DB;
- tabella users;
- tabella games;
- tabella matches;
- login;
- API GET `/games`;
- API GET `/matches`.

### Fase 3 — gestione partite

Implementare:

- creazione partita;
- apertura;
- partecipazione;
- avvio;
- terminazione;
- gestione stati.

### Fase 4 — simulatore freccette

Implementare il client Java CLI che:

- seleziona la partita;
- seleziona il giocatore;
- inserisce settore;
- inserisce moltiplicatore;
- invia il tiro al server.

### Fase 5 — applicazione utente

Implementare:

- login;
- elenco partite;
- join;
- visualizzazione punteggio;
- stato della partita.

### Fase 6 — interfaccia admin

Implementare:

- login admin;
- elenco giochi;
- elenco partite;
- creazione partita;
- gestione partita;
- visualizzazione punteggio.

### Fase 7 — documentazione

Completare:

- OpenAPI;
- Use Case Diagram;
- Class Diagram;
- Sequence Diagram;
- schema architetturale;
- README;
- istruzioni di avvio.

---

## 18. Estensioni opzionali

Solo se rimane tempo:

### MQTT

Sostituire:

```text
Simulator -> REST -> Server
```

con:

```text
Simulator -> MQTT -> Mosquitto -> Server/Edge
```

### Edge component

Aggiungere un processo locale che:

- riceve gli eventi del sensore;
- memorizza temporaneamente gli eventi;
- sincronizza il server centrale;
- supporta una modalità offline.

### Keycloak

Sostituire il token applicativo con:

```text
OAuth2 / JWT / Keycloak
```

### Applicazione Android

Trasformare il client Java desktop in un'app Android.

### Più giochi

Aggiungere in futuro:

```text
Calciobalilla
Bocce
Monopoli
```

senza modificare l'architettura centrale.

---

## 19. Scope finale consigliato per l'esame

La versione minima da considerare completa è:

```text
Connected Games Platform
|
+-- Server PHP
|   +-- login
|   +-- REST API
|   +-- DB
|   +-- admin web
|
+-- Freccette
|   +-- creazione partita
|   +-- join giocatori
|   +-- start
|   +-- registrazione tiri
|   +-- punteggio
|   +-- fine partita
|
+-- Client utente Java
|   +-- login
|   +-- lista partite
|   +-- join
|   +-- stato partita
|
+-- Simulatore freccettiera Java CLI
    +-- scelta partita
    +-- scelta settore
    +-- moltiplicatore
    +-- invio evento
```

Questa versione è abbastanza piccola da essere realizzabile, ma contiene comunque gli elementi principali richiesti dal corso:

- sistema distribuito;
- client/server;
- HTTP;
- REST;
- JSON;
- autenticazione;
- database;
- separazione delle responsabilità;
- simulazione IoT;
- possibilità di evoluzione verso MQTT.

---

## 20. Ordine pratico con cui partire

Prima di scrivere codice:

1. scegliere definitivamente **Slim oppure Yii2**;
2. definire lo schema del database;
3. definire gli endpoint REST;
4. definire gli stati della partita;
5. definire il comportamento della partita di freccette;
6. creare i diagrammi principali;
7. implementare il server;
8. implementare il simulatore;
9. implementare il client utente;
10. completare l'interfaccia admin;
11. documentare le API;
12. valutare solo alla fine MQTT o altre estensioni.

La priorità deve essere avere molto presto un flusso completo funzionante:

```text
Admin crea partita
        |
        v
Utente fa login
        |
        v
Utente entra nella partita
        |
        v
Admin avvia
        |
        v
Simulatore invia un tiro
        |
        v
Server aggiorna il punteggio
        |
        v
Client visualizza il nuovo stato
        |
        v
Partita termina
```

Questo rappresenta il **Minimum Viable Project** da completare prima di qualsiasi estensione.
