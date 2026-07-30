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
- `SOCIAL_PUBLISHING_DRY_RUN` deve essere `false` per gli invii reali.
- Ogni piattaforma configurata su un post automatico deve avere esattamente un
  account del cliente pronto alla pubblicazione.
- Le credenziali applicative Meta o TikTok devono essere configurate quando la
  relativa piattaforma è usata.
- Le credenziali Nextcloud devono essere presenti quando una versione corrente
  usa media Nextcloud.

## Sequenza di attivazione

1. Lasciare `SOCIAL_AUTO_PUBLISH_ENABLED=false`.
2. Avviare scheduler e worker delle code social.
3. Eseguire `php artisan social:production-readiness --allow-auto-disabled`.
4. Correggere ogni controllo indicato come `ERRORE`.
5. Impostare `SOCIAL_PUBLISHING_DRY_RUN=false`.
6. Impostare `SOCIAL_AUTO_PUBLISH_ENABLED=true`.
7. Ricaricare la configurazione dell’applicazione e dei worker.
8. Eseguire `php artisan social:production-readiness` senza opzioni.
9. Verificare il primo ciclo con `php artisan social:audit-runtime
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

I comandi di readiness e audit non effettuano chiamate ai provider. Il controllo
di readiness legge la configurazione e il database; registra soltanto
l’esecuzione del comando nel monitoraggio interno.
