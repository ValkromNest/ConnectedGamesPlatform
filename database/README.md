# Schemi per una nuova installazione

I tre file SQL descrivono lo stato finale delle tabelle utilizzate dai modelli applicativi:

| File | Destinazione predefinita |
| --- | --- |
| `connected_games_db.sql` | Database dell'app principale |
| `connected_games_identity.sql` | Database dei profili identity, inclusi i collegamenti Keycloak |
| `connected_games_dartsgame.sql` | Database del servizio Freccette |

I database devono essere già creati e completamente vuoti. Gli script non contengono `CREATE DATABASE`, `USE`, `DROP`, `TRUNCATE`, migrazioni o relativo storico. Creano tabelle InnoDB con charset utf8mb4, indici e vincoli interni a ciascun servizio. Non creano relazioni tra database.

Viene inserito soltanto il gioco Freccette con codice `darts`, necessario alla console. Non sono inclusi utenti, password, dispositivi o partite. Le tabelle legacy ancora utilizzate dai modelli identity vengono mantenute; le credenziali web si gestiscono in Keycloak.

## Comando di installazione

Dalla root del progetto, esclusivamente su una nuova installazione:

```powershell
php yii database/install
```

Il comando si collega prima al server senza selezionare il database, crea i database mancanti con charset utf8mb4 e collation utf8mb4_general_ci, quindi legge i tre file e usa le connessioni definite in `config/db.php`, `services/identity-service/config/db.php` e `services/darts-game-service/config/db.php`. I nomi dei database effettivi sono quelli configurati nelle connessioni: gli script SQL non impongono un nome.

Le variabili `IDENTITY_DB_*` e `DARTS_GAME_DB_*` devono essere disponibili nel terminale, che non eredita quelle delle configurazioni Run di PhpStorm. L'account MySQL di ciascuna connessione deve poter creare database e tabelle, indici, foreign key e inserire il catalogo iniziale. Con account limitati senza privilegio CREATE DATABASE, predisporre i database e concordare i privilegi con l�amministratore prima di usare questa procedura.

Prima di qualsiasi scrittura vengono verificati tutti e tre i database. Se uno contiene tabelle o viste, non è accessibile oppure due connessioni puntano allo stesso database, il comando si interrompe. Non esiste un'opzione per sovrascrivere database popolati.

MySQL applica le istruzioni DDL con commit impliciti: un errore durante la creazione può lasciare uno schema parziale, senza rollback automatico né cancellazioni. Prima di riprovare occorre controllare manualmente i database della nuova installazione.

Questa procedura sostituisce l'installazione tramite migrazioni: non eseguire poi le vecchie migrazioni sulle tabelle già create, perché lo storico non viene registrato. Per una futura strategia di aggiornamento tramite migrazioni sarà necessario definire separatamente una baseline.

Per l'importazione manuale, creare prima il database e selezionare il database corretto in phpMyAdmin e importare il relativo file. Non usare questi script per aggiornare database esistenti.

Il file preesistente `test-databases.sql` riguarda l'ambiente di test e non viene utilizzato dal comando.
