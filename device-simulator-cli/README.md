# Simulatore CLI Freccette per Windows

Simulatore C++17 per Windows che comunica con il server Yii2 via HTTP. Usa WinHTTP di Windows e non richiede Java, Maven o librerie esterne.

## Requisiti

- Windows
- MinGW-w64 con `g++` nel `PATH`
- Server Yii2 raggiungibile via HTTP
- Dispositivo Freccette abilitato e partita assegnata dal gestionale

## Avvio

Da PowerShell, nella cartella del simulatore:

```powershell
.\run.ps1
```

Oppure, dal Prompt dei comandi:

```bat
run.bat
```

Gli script compilano `src/main.cpp` usando il compilatore già presente e collegano WinHTTP. Si può anche passare l'indirizzo del server:

```powershell
.\run.ps1 http://127.0.0.1:8080
```

Senza argomento, il programma chiede l'URL base, poi mostra i dispositivi Freccette abilitati per la selezione. Il token viene mantenuto in memoria per la sessione e non viene salvato su disco.

## Comandi

- `t`: inserisce un tiro; player e turno sono letti dal server.
- `s`: mostra subito lo stato aggiornato.
- `q`: chiude il simulatore.

Il simulatore interroga periodicamente lo stato per rilevare avvio, aggiornamenti dei punteggi e termine della partita. Non calcola i punteggi e non accede al database.

## API usate

- `GET /index.php?r=api/darts-api/devices`
- `POST /index.php?r=api/darts-api/session`
- `GET /index.php?r=api/darts-api/state`
- `POST /index.php?r=api/darts-api/throw`

Il pairing corrente è aperto: chiunque raggiunga il server può ricevere il token di un dispositivo Freccette abilitato.
