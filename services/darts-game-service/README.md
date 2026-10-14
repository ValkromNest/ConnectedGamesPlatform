# Darts game service

Servizio Yii2 autonomo per inizializzare e gestire partite 501. Il codice vive
fuori dalle directory XAMPP; XAMPP fornisce soltanto MySQL. La connessione
predefinita usa il database connected_games_dartsgame su localhost.

## Avvio in PhpStorm

Aprire questa directory come progetto/modulo PhpStorm, impostare l'interprete
PHP 8.2 o successivo e installare le dipendenze con Composer in questa cartella.
Configurare il run configuration PHP Script con `yii` come file, la directory
del servizio come working directory e questi argomenti:

```text
serve 127.0.0.1:8003 --docroot=web --router=web/index.php
```

Il router `web/index.php` è necessario perché il server PHP integrato inoltri a
Yii le route API che non corrispondono a file statici.

Prima dell'avvio, creare connected_games_dartsgame in MySQL XAMPP e applicare
le migrazioni:

```powershell
php yii migrate --migrationPath=@darts/migrations
```

Per credenziali diverse dai valori XAMPP locali, configurare
DARTS_GAME_DB_HOST, DARTS_GAME_DB_PORT, DARTS_GAME_DB_NAME,
DARTS_GAME_DB_USER e DARTS_GAME_DB_PASSWORD nell'ambiente del run
configuration PhpStorm.

Nel run configuration del monolite, impostare DARTS_GAME_SERVICE_URL a
http://127.0.0.1:8003 e impostare DARTS_GAME_MATCHES_TOKEN e
DARTS_GAME_DEVICE_TOKEN. Configurare gli stessi due token nel run
configuration di questo servizio. Usare valori locali robusti e non salvarli
nei file del repository.

Le API richiedono token server-to-server distinti:

- PUT /api/v1/games/darts/matches/{matchId}/context: token
  DARTS_GAME_MATCHES_TOKEN.
- GET /api/v1/games/darts/matches/{matchId}/state e
  POST /api/v1/games/darts/matches/{matchId}/throws: token
  DARTS_GAME_DEVICE_TOKEN.

Le chiavi e gli identificativi partita/player sono opachi. Il servizio non
accede al database del monolite o a quelli di altri servizi.
