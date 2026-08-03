# Pubblicazione social e Meta

## Stato attuale

Il motore di pubblicazione è implementato per Facebook, Instagram e TikTok. La
produzione Meta resta congelata finché non sono disponibili l'app autorizzata,
le credenziali definitive e un collaudo reale controllato.

Sono già presenti:

- OAuth dell'agenzia;
- sincronizzazione di Pagine Facebook e account Instagram professionali;
- assegnazione degli asset ai clienti;
- pubblicazione manuale e automatica;
- code dedicate;
- snapshot immutabili di versione, target e media;
- verifica di integrità e preflight;
- idempotenza e catene di retry;
- classificazione degli errori temporanei, permanenti e da revisione manuale;
- polling dei container Instagram e degli invii TikTok;
- dashboard operativa, readiness e audit storico.

Non è ancora dimostrato il funzionamento end-to-end con l'app Meta reale. I
test automatici simulano il provider e non sostituiscono App Review, OAuth,
permessi effettivi e pubblicazione su asset reali.

## Architettura del flusso

1. Il marketing crea o rigenera il contenuto tramite n8n.
2. Una versione diventa corrente e viene approvata internamente o dal cliente,
   secondo le regole della campagna.
3. Per la modalità automatica, data e ora devono essere complete e lo scheduler
   deve trovare il post autorizzato.
4. Il sistema richiede esattamente un account pronto per cliente e piattaforma.
5. Prima dell'invio crea una `publication` con snapshot di testo, media,
   versione e target.
6. Verifica hash, associazioni, disponibilità dei media e requisiti della
   piattaforma.
7. Accoda l'esecuzione su `social-publishing`.
8. Facebook restituisce normalmente un esito sincrono; Instagram e TikTok
   possono richiedere riconciliazione asincrona.
9. Lo stato del post viene sincronizzato con l'esito delle singole piattaforme.

Gli stati principali della publication sono:

- `pending`;
- `publishing`;
- `published`;
- `failed`;
- `needs_manual_review`;
- `superseded`;
- `abandoned`;
- `cancelled`.

Un retry crea un nuovo tentativo collegato alla radice e conserva lo snapshot
originale; non modifica retroattivamente ciò che era stato autorizzato.

## Kill switch e dry-run

```dotenv
SOCIAL_PUBLISHING_DRY_RUN=true
SOCIAL_AUTO_PUBLISH_ENABLED=false
```

I due flag hanno scopi diversi:

- `SOCIAL_AUTO_PUBLISH_ENABLED=false` impedisce allo scheduler di creare nuove
  pubblicazioni automatiche;
- `SOCIAL_PUBLISHING_DRY_RUN=true` impedisce al publisher di inviare realmente
  il contenuto al provider e produce un esito simulato.

Il kill switch automatico non annulla publication già create o job già
accodati e non blocca necessariamente un'azione manuale. In un'emergenza reale:

1. disattivare l'automazione;
2. ricaricare configurazione e scheduler;
3. fermare temporaneamente i worker social;
4. inventariare job e publication già presenti;
5. decidere singolarmente se riprendere, annullare o sottoporre a revisione.

Non cancellare direttamente job o publication senza averne verificato lo stato
presso il provider: un invio interrotto può avere un esito ambiguo.

## Prerequisiti infrastrutturali

- `APP_URL` pubblico e HTTPS.
- Coda asincrona operativa.
- Worker sulle code `social-publishing` e `social-reconciliation`.
- Scheduler Laravel attivo ogni minuto.
- Heartbeat recente per tutte le code monitorate.
- Disco `social_media` privato per media locali e generati da n8n.
- URL firmati raggiungibili dai provider per il tempo necessario.
- n8n configurato con Bearer, firma HMAC e idempotenza.
- Nextcloud configurato quando la versione usa media remoti.
- Esattamente un account pronto per ogni coppia cliente-piattaforma usata.

## Configurazione Meta

Variabili già supportate:

```dotenv
META_CLIENT_ID=
META_CLIENT_SECRET=
META_CONFIG_ID=
META_REDIRECT_URI=https://gestionale.example.it/admin/social/connections/meta/callback
META_GRAPH_VERSION=v25.0
META_CONNECT_TIMEOUT=5
META_HTTP_TIMEOUT=15
META_MAX_SYNC_PAGES=25
```

Il redirect deve coincidere esattamente con quello configurato nel pannello
Meta, inclusi schema HTTPS, dominio, percorso e slash finali.

`META_CONFIG_ID` deve contenere l'ID della configurazione creata in Facebook
Login for Business. Il flusso implementato usa un token di accesso dell'utente.

Il codice richiede durante OAuth:

- `pages_manage_posts`;
- `pages_read_engagement`;
- `pages_show_list`;
- `business_management`;
- `instagram_basic`;
- `instagram_content_publish`.

Il flusso non richiede `email` né `pages_manage_metadata`: il primo non è
necessario per il collegamento degli asset e il secondo serve ai webhook delle
Pagine, che questa implementazione non utilizza.

Prima della produzione verificare nel pannello Meta quali permessi richiedono
accesso avanzato, App Review, verifica aziendale o ulteriori adempimenti per lo
specifico tipo di app. Questi requisiti sono decisi da Meta e possono cambiare
senza modifiche al repository.

Per Instagram, l'account deve essere professionale e associato a una Pagina
Facebook gestibile dall'utente che completa OAuth. Gli account consumer non
sono compatibili con il flusso implementato.

## Collegamento degli asset

1. Accedere come admin.
2. Aprire le connessioni social dell'agenzia.
3. Avviare il collegamento Meta.
4. Completare il consenso con un utente che gestisce le Pagine necessarie.
5. Verificare che la sincronizzazione trovi Pagine e account Instagram.
6. Controllare che ogni asset abbia capacità di pubblicazione e token valido.
7. Assegnare al cliente un solo asset pronto per ciascuna piattaforma.
8. Eseguire nuovamente la sincronizzazione e controllare che nessun asset sia
   stato revocato o privato dei permessi.

I token sono cifrati nel database. Non copiarli nei log, nella documentazione o
nei ticket.

## Media e contenuti

- Facebook può pubblicare testo, immagine, video e raccolte di immagini secondo
  il percorso selezionato dal publisher.
- Instagram richiede almeno un media.
- Un Reel Instagram richiede un video.
- Carousel e video usano container asincroni e devono completare il polling
  prima che il contenuto sia considerato pubblicato.
- I media devono essere raggiungibili dal provider tramite HTTPS.
- Lo snapshot include dimensione, hash o ETag quando disponibili; una modifica
  successiva può bloccare l'invio.

I limiti effettivi di formato, durata, aspect ratio, numero di elementi e quota
devono essere verificati nuovamente nelle API Meta al momento del collaudo.

## Sequenza di collaudo Meta

### Fase 1: ambiente congelato

1. Lasciare dry-run attivo e automazione disattivata.
2. Configurare app, redirect e permessi.
3. Collegare l'account agenzia e sincronizzare gli asset.
4. Assegnare un asset di prova a un cliente controllato.
5. Verificare che la UI non esponga errori tecnici o token.
6. Eseguire test automatici, monitoraggio e audit.

### Fase 2: readiness senza automazione

1. Fare backup di database e media.
2. Scegliere un contenuto non critico con testo e media conformi.
3. Impostare `SOCIAL_PUBLISHING_DRY_RUN=false`.
4. Mantenere `SOCIAL_AUTO_PUBLISH_ENABLED=false`.
5. Ricostruire la cache di configurazione e riavviare i worker.
6. Eseguire:

```bash
php artisan social:production-readiness --allow-auto-disabled
```

7. Correggere ogni controllo segnato come `ERRORE`.

### Fase 3: prima pubblicazione reale

1. Eseguire una sola pubblicazione manuale sul cliente di prova.
2. Verificare stato locale, contenuto sul social e identificativo esterno.
3. Per Instagram attendere la conclusione del container e della
   riconciliazione.
4. Verificare che non esistano duplicati.
5. Controllare dashboard, log sanitizzati e job falliti.
6. Provare almeno un errore controllato e un retry.

### Fase 4: automazione

Soltanto dopo il collaudo manuale:

```dotenv
SOCIAL_AUTO_PUBLISH_ENABLED=true
```

Ricostruire la cache, riavviare i worker ed eseguire:

```bash
php artisan social:production-readiness
php artisan social:audit-runtime --fail-on-actionable
```

Sorvegliare almeno il primo ciclo programmato e confrontare ogni piattaforma con
il relativo record locale.

## Comandi operativi

```bash
php artisan monitor:system
php artisan social:production-readiness --allow-auto-disabled
php artisan social:production-readiness
php artisan social:audit-runtime --fail-on-actionable
php artisan social:audit-historical-integrity
php artisan social:sync-accounts
php artisan social:refresh-agency-connections
php artisan social:sync-post-publication-statuses
php artisan social:fail-stale-publications
```

Retry controllato di una publication fallita o in revisione:

```bash
php artisan social:retry-publication ID_PUBLICATION
```

Prima del primo go-live verificare i media pubblici e, se necessario, seguire
la migrazione descritta nella guida di distribuzione.

## Gestione degli errori

| Classificazione | Esempio | Azione |
| --- | --- | --- |
| Temporaneo | timeout, errore 5xx, rate limit | Attendere e riprovare con backoff. |
| Permanente | formato media non valido, requisito del contenuto non rispettato | Correggere il contenuto e creare una nuova publication. |
| Revisione manuale | token revocato, permesso mancante, esito ambiguo | Non reinviare alla cieca; confrontare provider e gestionale. |

Un `401` o `403` richiede normalmente un controllo di token e permessi. Un
`429` richiede attesa e riduzione del ritmo. Un timeout dopo l'invio può essere
ambiguo: cercare prima l'eventuale contenuto già creato.

## TikTok

TikTok usa configurazione e autorizzazioni separate. Il codice supporta modalità
`draft` e `direct`, ma il go-live richiede che mock publishing sia disattivato,
che la modalità sia autorizzata dal provider e che venga eseguito un collaudo
dedicato. L'approvazione Meta non abilita TikTok.

## Riferimenti ufficiali

- [Workspace ufficiale Meta per le API Facebook](https://www.postman.com/meta/facebook/overview)
- [Documentazione ufficiale Meta delle API Facebook su Postman](https://www.postman.com/meta/facebook/documentation/r56bjfd/facebook-api)
- [Workspace ufficiale Meta per le API Instagram](https://www.postman.com/meta/instagram/overview)
- [Documentazione ufficiale Meta delle API Instagram su Postman](https://www.postman.com/meta/instagram/documentation/6yqw8pt/instagram-api)
- [Meta for Developers: permessi](https://developers.facebook.com/docs/permissions/)
- [Meta for Developers: pubblicazione Instagram](https://developers.facebook.com/docs/instagram-platform/instagram-api-with-facebook-login/content-publishing/)
