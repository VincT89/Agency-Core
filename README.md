# Agency Core

Gestionale interno di Sodano Consulting per clienti, progetti, attività, ticket,
calendario, campagne social, shooting, fatture, pagamenti, hosting e controllo
operativo. L'applicazione è costruita con Laravel, Blade, Livewire, Alpine.js e
Vite.

## Stato del progetto

Stato verificato il 31 luglio 2026.

| Area | Stato | Nota |
| --- | --- | --- |
| Gestionale operativo | Pronto | Clienti, progetti, task, ticket, calendario, spese, hosting, fatture e pagamenti sono coperti dalla suite automatica. |
| Nextcloud | Pronto e collegato | Quando il cliente ha un nome cartella vengono predisposte le directory foto e video; se Nextcloud non risponde, la creazione del cliente viene interrotta. |
| Shooting | Pronto | Il marketing propone le date, il fotografo risponde e il marketing registra il contatto e la risposta del cliente. Non esiste un portale cliente. |
| Notifiche e registro attività | Pronto | Notifiche personali e campanella ticket restano separate. Il registro completo è visibile soltanto agli amministratori. |
| n8n | Pronto lato contratto | Generazione e rigenerazione post, callback protette, ticket e messaggi chatbot sono documentati e testati. |
| Pubblicazione social | Pronta lato codice, collaudo esterno pendente | Motore, code, snapshot, controlli, retry e dashboard sono presenti. Meta richiede ancora configurazione dell'app, autorizzazioni e test reali. |
| Fatturazione elettronica | Pronta lato codice, collaudo Aruba pendente | XML FatturaPA, verifica senza invio, upload protetto, callback, polling e cronologia sono presenti. Servono account Premium o delega, credenziali e ciclo DEMO prima della produzione. |

L'ultima verifica completa ha superato 719 test con 2.085 asserzioni; un test è
stato escluso perché specifico di un diverso motore database. Questo risultato
riduce il rischio di regressioni, ma non sostituisce i collaudi reali con Meta,
Aruba, n8n e Nextcloud.

## Funzionalità principali

- Anagrafica clienti con dati fiscali, contatti, logo e cartelle Nextcloud.
- Progetti, team, assegnazioni e perimetro di accesso per progetto.
- Task e ticket con priorità, scadenze, checklist, commenti e notifiche.
- Calendario condiviso e generazione automatica di eventi dagli shooting.
- Campagne marketing, piano editoriale, versioni dei post, media e revisione
  tramite collegamento pubblico firmato.
- Generazione e rigenerazione dei contenuti tramite n8n.
- Pubblicazione Facebook, Instagram e TikTok tramite code asincrone, con
  snapshot immutabili, controlli preventivi, retry e revisione manuale.
- Shooting interno tra marketing e fotografo, con contatto cliente gestito
  esternamente tramite email, WhatsApp, telefono o altro canale.
- Fatture gestionali, voci, IVA, pagamenti, scadenze e riepilogo economico.
- Preparazione fiscale TD01, numerazione progressiva, XML FatturaPA, verifica
  Aruba senza invio, trasmissione controllata e ricezione degli esiti SdI.
- Hosting, domini, rinnovi e interventi tecnici.
- Notifiche personali, campanella ticket e registro attività amministrativo.
- Monitoraggio di scheduler, code, job falliti e processi operativi.

Il perimetro dettagliato e i limiti sono descritti in
[Ambito funzionale](docs/functional-scope.md).

## Ruoli

I ruoli disponibili sono:

- `admin`: amministrazione completa del sistema, utenti, registro attività,
  connessioni social e visibilità globale;
- `administration`: clienti, progetti, fatture, pagamenti, spese e dati fiscali;
- `operations_manager`: gestione operativa trasversale e visibilità globale sui
  progetti;
- `marketing`: campagne, post, revisione, shooting e operazioni social nel
  proprio perimetro;
- `photographer`: shooting assegnati, task e progetti autorizzati;
- `developer`: task, ticket, hosting e progetti autorizzati;
- `graphic_designer`: task, ticket e progetti autorizzati.

Le policy applicative restano la fonte definitiva. In particolare, soltanto
`admin` può vedere chi ha effettuato l'accesso e chi ha compiuto le operazioni
registrate nel sistema.

## Requisiti tecnici

- PHP 8.3 o successivo con le estensioni richieste da Laravel.
- Composer.
- Node.js e npm.
- MySQL in produzione.
- Un driver di coda asincrono; la configurazione predefinita usa il database.
- Scheduler Laravel eseguito ogni minuto.
- HTTPS pubblico per callback, OAuth e consegna temporanea dei media.

## Installazione locale

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Su sistemi Unix sostituire `copy` con `cp`.

Il comando `composer run dev` avvia il server locale, Vite, log e un worker
generico. Per provare pubblicazione e monitoraggio devono essere avviati anche
worker che ascoltino `chatbot`, `social-publishing` e
`social-reconciliation`.

In produzione, quando non e disponibile un supervisore dedicato, lo scheduler
Laravel avvia ogni minuto un worker breve che elabora `social-publishing`,
`social-reconciliation`, `chatbot` e `default`, quindi termina quando le code
sono vuote. In questo caso lo scheduler deve essere realmente attivo sul server.

Non eseguire `php artisan db:seed` in produzione senza indicare una classe: il
`DatabaseSeeder` richiama `DemoDataSeeder`, che crea utenti e dati
dimostrativi. Il seeder dedicato alla pulizia è descritto in
[Pulizia dei dati dimostrativi](docs/data-cleanup.md).

## Configurazione essenziale

Usare `.env.example` come punto di partenza per le variabili più comuni e
conservare i segreti soltanto nel gestore sicuro dell'ambiente di esecuzione.
Le guide di integrazione riportano anche opzioni supportate ma non ancora
presenti nell'esempio.

- Applicazione: `APP_ENV`, `APP_DEBUG`, `APP_URL`, database, sessioni, cache,
  posta e code.
- n8n: token Bearer, segreto HMAC, idempotenza e URL dei webhook.
- Nextcloud: URL, credenziali WebDAV e radici foto/video.
- Meta: App ID, App Secret, redirect OAuth e versione Graph API.
- TikTok: credenziali, redirect, modalità di consegna e kill switch.
- Social: dry-run, pubblicazione automatica e soglie operative.
- Aruba: ambiente, abilitazione, credenziali Premium, chiave callback, verifica
  obbligatoria e blocco separato dell'invio effettivo. Le variabili operative
  sono riportate in `.env.example` e nella guida dedicata.

## Verifiche di sviluppo

```bash
php artisan test --compact
npm run build
php artisan view:cache
git diff --check
```

Per la pubblicazione social sono inoltre disponibili:

```bash
php artisan monitor:system
php artisan social:production-readiness --allow-auto-disabled
php artisan social:audit-runtime --fail-on-actionable
php artisan social:audit-historical-integrity
```

## Produzione

La procedura completa è in [Distribuzione in produzione](docs/production-deployment.md).
I punti non negoziabili sono:

1. backup coerente di database e storage;
2. `APP_ENV=production`, `APP_DEBUG=false` e `APP_URL` HTTPS;
3. migrazioni applicate prima di riaprire il traffico;
4. scheduler attivo ogni minuto;
5. worker attivi su tutte le code monitorate;
6. cache ricostruite e worker riavviati dopo ogni rilascio;
7. smoke test delle funzioni principali e controllo dei log;
8. pubblicazione automatica lasciata disattivata finché Meta non supera il
   collaudo reale;
9. `ARUBA_EINVOICING_ALLOW_SEND=false` finché il ciclo DEMO, le callback e la
   prima fattura reale non sono stati autorizzati e verificati.

## Documentazione

L'indice completo è disponibile in [docs/README.md](docs/README.md).

- [Ambito funzionale e limiti](docs/functional-scope.md)
- [Distribuzione in produzione](docs/production-deployment.md)
- [Pulizia dei dati dimostrativi](docs/data-cleanup.md)
- [Integrazione Nextcloud](docs/nextcloud.md)
- [Flusso shooting](docs/shooting-workflow.md)
- [Pubblicazione social e Meta](docs/social-production-readiness.md)
- [Contratto n8n](docs/n8n-contract.md)
- [Fatturazione elettronica e Aruba](docs/electronic-invoicing-aruba.md)

## Limiti attuali

- Non esiste un portale cliente generale. È presente soltanto il collegamento
  pubblico, firmato e limitato, per la revisione dei post marketing.
- Il fotografo gestisce esternamente esecuzione e consegna dello shooting; il
  gestionale coordina richiesta, disponibilità, conferma, task e calendario.
- Meta non è considerato pronto per la produzione finché OAuth, asset reali e
  primo ciclo di pubblicazione non vengono verificati con l'app approvata.
- La fatturazione elettronica è pronta lato codice, ma non è dichiarata pronta
  in produzione finché non supera DEMO, callback e primo invio reale con
  credenziali Aruba.
- Il perimetro fiscale supporta TD01/FPR12 in EUR; non comprende ancora PA,
  note di credito, ritenute, casse previdenziali, split payment, bollo e
  rateazioni multiple.
- La cancellazione di un cliente dal database non elimina automaticamente le
  cartelle remote Nextcloud.
- I contenuti e le integrazioni future possono introdurre nuovi casi non coperti
  dalle verifiche visuali e automatiche attuali.

## Sicurezza operativa

- Non committare mai `.env`, token, password, API key o file di backup.
- Non riportare segreti nei log o nei ticket.
- Usare credenziali dedicate e, dove disponibile, password applicative.
- Mantenere firma HMAC e idempotenza obbligatorie per n8n in produzione.
- Conservare `SOCIAL_AUTO_PUBLISH_ENABLED=false` come arresto immediato delle
  nuove pubblicazioni automatiche.
- Eseguire ogni cancellazione massiva soltanto dopo backup, inventario e
  conferma esplicita dell'ambiente e del perimetro.
