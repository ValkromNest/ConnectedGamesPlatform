# Simulatore locale di Freccette

Questa guida descrive come usare i comandi console per associare player già creati a una partita e simulare tiri secondo il formato 501. Il simulatore usa i servizi applicativi del server: non accede direttamente al database e non calcola punteggi separatamente.

## Prerequisiti

- Il server Yii2 è configurato e il database è raggiungibile.
- I player esistono già in `tbl_user` con ruolo `player`. Per crearne uno: `php yii player/create <username>`.
- La partita di Freccette è stata creata dal gestionale.

## Procedura

1. Nel gestionale, apri la partita. Lo stato deve essere `OPEN` per aggiungere partecipanti.
2. Aggiungi ciascun player con il suo username esatto:

   ```powershell
   php yii simulatore/add-player <idPartita> <username>
   ```

   Esempio:

   ```powershell
   php yii simulatore/add-player 12 alice
   php yii simulatore/add-player 12 bob
   ```

   L’ID della partita è visibile nell’URL della pagina di dettaglio. Il comando rifiuta player inesistenti, duplicati e partecipazioni oltre il massimo previsto dalla partita.

3. Nel gestionale, avvia la partita. Il simulatore accetta tiri solo nello stato `RUNNING`. Per le nuove regole usa una partita nuova: quelle iniziate con il precedente punteggio ad accumulo non sono compatibili.
4. Avvia la sessione interattiva:

   ```powershell
   php yii simulatore/play <idPartita>
   ```

   A ogni freccetta il comando mostra il player che deve tirare. Ogni player tira fino a tre freccette consecutive; poi il turno passa all'altro player. Inserisci un settore da `1` a `20` oppure `25` per il bull, poi un moltiplicatore. Per il bull usa `1` (25 punti) o `2` (50 punti); il triplo bull non esiste. Inserisci `q` al prompt del settore per terminare il simulatore e lasciare la partita in corso.

5. Per terminare la partita, usa **Termina partita** nel gestionale.

## Regole applicate

- Ogni player parte da 501; il punteggio dei tiri viene sottratto dai punti rimanenti.
- Per vincere bisogna arrivare esattamente a zero con un doppio (`moltiplicatore = 2`); il bull interno vale come doppio 25.
- Si fa bust se il tiro supera il punteggio rimanente, lascia 1, o arriva a zero senza un doppio. Il punteggio torna a quello di inizio visita e il turno passa.
- Le visite alternano i partecipanti secondo `turn_order`, fino a tre freccette consecutive; il turno corrente è ricostruito dai tiri registrati.
- Validazione del tiro, salvataggio e aggiornamento del punteggio avvengono nel servizio del server, in un’unica transazione.
- Al checkout valido il server termina automaticamente la partita. L'admin può comunque terminarla manualmente dal gestionale.

## Comandi rapidi

```powershell
php yii player/create <username>
php yii simulatore/add-player <idPartita> <username>
php yii simulatore/play <idPartita>
```
