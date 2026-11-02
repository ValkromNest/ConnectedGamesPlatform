# Login web con Keycloak

Il darts-service e i dispositivi non cambiano. La console resta riservata ai profili locali admin; i ruoli locali sono la fonte dei permessi applicativi. I ruoli realm sono predisposti per estensioni e non sostituiscono automaticamente quelli locali.

## Avvio locale

1. Impostare KC_BOOTSTRAP_ADMIN_PASSWORD nell'ambiente di Docker Compose e avviare `docker compose up -d keycloak`.
2. Aprire http://localhost:8080 e accedere con admin e la password impostata. Il realm connected-games è importato al primo avvio.
3. Nel client connected-games-web, scheda Credentials, recuperare il client secret generato. Nessun segreto è incluso nel repository.
4. Impostare queste variabili nell'ambiente dei processi PHP (console web e identity-service); riavviarli dopo le modifiche:

```text
KEYCLOAK_ISSUER=http://localhost:8080/realms/connected-games
KEYCLOAK_CLIENT_ID=connected-games-web
KEYCLOAK_CLIENT_SECRET=<segreto del client>
KEYCLOAK_AUDIENCE=connected-games-web
KEYCLOAK_REDIRECT_URI=http://localhost:8000/index.php?r=login%2Fcallback
KEYCLOAK_LOGOUT_URI=http://localhost:8000/index.php?r=login%2Findex
```

Questi URL presuppongono PHP eseguito sul computer locale. Dentro Docker, localhost identifica il container: predisporre un hostname Keycloak raggiungibile sia dal browser sia dai processi PHP e aggiornare issuer, configurazione hostname Keycloak e redirect. Non disabilitare la verifica TLS; usare HTTPS fuori dall'ambiente locale. La modalità start-dev è solo per sviluppo.

5. Dalla directory services/identity-service eseguire `php yii migrate` (richiede il database configurato).
6. Creare gli utenti in Keycloak e impostarne le password. Per ogni profilo locale esistente recuperare l'ID utente dalla console Keycloak e associarlo:

```text
php yii identity/link-keycloak <username-locale> <id-utente-keycloak> http://localhost:8080/realms/connected-games
```

Il subject_id applicativo rimane invariato. Non si associano account automaticamente per username. Per nuovi profili è ancora disponibile identity/create-user; la password locale creata non viene utilizzata dal login web. Gli endpoint legacy login/logout sono rimossi: nessun fallback a password locali. Le vecchie tabelle dei token rimangono per compatibilità di migrazione, ma quei token non sono più accettati.

## Funzionamento e verifica

Authorization Code con PKCE S256 e state monouso con durata di dieci minuti; token e refresh token nella sessione PHP. Il backend identity verifica l'access token tramite introspezione autenticata Keycloak, controllando active, issuer, audience e scadenza, quindi cerca il profilo locale abilitato. Nessuna verifica JWT implementata manualmente. Il rinnovo avviene al recupero dell'identità Yii; il logout revoca il refresh token e apre il logout Keycloak (che pu� richiedere conferma).

Riferimenti: https://www.keycloak.org/securing-apps/oidc-layers e https://www.keycloak.org/server/containers
