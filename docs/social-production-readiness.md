# Pubblicazione social automatica: messa in produzione

La pubblicazione automatica resta disattivata finché
`SOCIAL_AUTO_PUBLISH_ENABLED` è `false`. Il flag è il kill switch operativo:
quando è disattivato, lo scheduler continua a funzionare ma non crea né accoda
nuove publication automatiche.

## Prerequisiti

- `APP_URL` deve essere pubblico e HTTPS.
- `QUEUE_CONNECTION` deve usare un driver asincrono. I worker devono ascoltare
  almeno le code `social-publishing` e `social-reconciliation`.
- Lo scheduler Laravel deve essere attivo ogni minuto.
- Tutte le code monitorate devono aver prodotto un heartbeat recente.
- `SOCIAL_PUBLISHING_DRY_RUN` deve essere `false` per gli invii reali.
- Le callback n8n devono usare Bearer, firma HMAC e `Idempotency-Key`.
- I media locali e generati da n8n devono trovarsi sul disco privato
  `social_media`.
- Ogni piattaforma configurata su un post automatico deve avere esattamente un
  account del cliente pronto alla pubblicazione.
- Le credenziali applicative Meta o TikTok devono essere configurate quando la
  relativa piattaforma è usata.
- Le credenziali Nextcloud devono essere presenti quando una versione corrente
  usa media Nextcloud.

## Sequenza di attivazione

1. Lasciare `SOCIAL_AUTO_PUBLISH_ENABLED=false`.
2. Avviare scheduler e worker delle code social.
3. Eseguire `php artisan social:migrate-media-to-private` e controllare il
   conteggio; applicare con `--execute`.
4. Attendere un ciclo di heartbeat e verificare `php artisan monitor:system`.
5. Eseguire `php artisan social:production-readiness --allow-auto-disabled`.
6. Correggere ogni controllo indicato come `ERRORE`.
7. Impostare `SOCIAL_PUBLISHING_DRY_RUN=false`.
8. Impostare `SOCIAL_AUTO_PUBLISH_ENABLED=true`.
9. Ricaricare la configurazione dell’applicazione e dei worker.
10. Eseguire `php artisan social:production-readiness` senza opzioni.
11. Verificare il primo ciclo con `php artisan social:audit-runtime
   --fail-on-actionable`.

## Arresto immediato

Per impedire nuove publication automatiche:

1. impostare `SOCIAL_AUTO_PUBLISH_ENABLED=false`;
2. ricaricare la configurazione dell’applicazione e dei processi scheduler;
3. non cancellare publication già accodate: vanno esaminate e gestite tramite
   il cruscotto operativo o i comandi di audit.

Il kill switch non annulla job già presenti in coda e non modifica gli snapshot
immutabili esistenti.

## Controlli operativi

- `php artisan social:production-readiness`: configurazione di go-live,
  scheduler, code, publication bloccate e target automatici.
- `php artisan social:audit-runtime --fail-on-actionable`: fallimenti,
  revisioni manuali e publication stale.
- `php artisan social:audit-historical-integrity`: consistenza storica di
  snapshot, versioni, media e retry.
- `php artisan monitor:system`: heartbeat, code e job falliti.
- `php artisan system:prune-operational-logs --dry-run`: anteprima della
  retention dei log; la pulizia effettiva è pianificata ogni notte.

## Rollback

Prima del deploy conservare il rilascio precedente e un backup consistente del
database. Le migration devono essere applicate prima di aprire il traffico.
Se il collaudo fallisce, disattivare la pubblicazione automatica, fermare i
worker del nuovo rilascio, ripristinare database e storage dal backup e
riavviare il rilascio precedente. Non eseguire il rollback del solo codice dopo
la migrazione dei media: database, disco `social_media` e codice devono restare
coerenti.

I comandi di readiness e audit non effettuano chiamate ai provider. Il controllo
di readiness legge la configurazione e il database; registra soltanto
l’esecuzione del comando nel monitoraggio interno.
