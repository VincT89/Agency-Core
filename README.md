# Sodano Consulting - Gestionale (Agency-Core)

Sistema gestionale interno per Sodano Consulting, progettato per orchestrare le operazioni dell'agenzia, i flussi di lavoro verticali e le relazioni con i clienti. Sviluppato su stack Laravel con frontend ibrido (Blade, Alpine.js, Livewire) e un'architettura CSS modulare.

## Architettura e Moduli Principali

Il sistema si basa su una rigorosa architettura incentrata sul concetto di "Project Supremacy", garantendo che gli utenti accedano esclusivamente alle risorse di loro pertinenza.

### Core Foundation
- **Clienti e Progetti**: Struttura gerarchica per l'organizzazione del lavoro.
- **Gestione Accessi (RBAC)**: Ruoli definiti (Admin, System Admin, Developer, Marketing, Photographer, Graphic Designer, Administration).
- **Task Management**: Assegnazione e monitoraggio di attività operative con scadenze.
- **Ticketing**: Tracciamento di anomalie e richieste di supporto legate ai progetti.
- **Calendario**: Pianificazione centralizzata degli eventi (riunioni interne, scadenze, appuntamenti clienti). Integrazione nativa dei link di videochiamata (Nextcloud Talk).

### Flussi Verticali
- **Modulo Shooting**: Gestione completa del ciclo di vita fotografico. Comprende la proposizione di slot orari, l'accettazione da parte del cliente, la generazione automatica di task ed eventi a calendario, fino alla consegna e archiviazione sicura degli asset.
- **Amministrazione e pagamenti**: registrazione interna di fatture e pagamenti, riepiloghi economici e sincronizzazione dello stato delle fatture. Il repository non include checkout Stripe o PayPal.

### UI/UX e Frontend
- **Design System Custom**: CSS modulare suddiviso per responsabilità (`_shell.css`, `_auth.css`, `_canvas-bg.css`, ecc.) integrato tramite Vite.
- **Auth Layout**: Struttura asimmetrica 2/3 - 1/3 con canvas generativo interattivo per valorizzare il brand aziendale.
- **Micro-interattività**: Gestita tramite Alpine.js per modali, dropdown e form dinamici, limitando l'uso di JS complesso.
- **Livewire**: Utilizzato selettivamente per componenti reattivi asincroni nella dashboard (es. panoramica shooting).

---

## Deploy e Configurazione Produzione

Prima di esporre l'applicazione in ambiente di produzione, è obbligatorio completare la seguente checklist per garantire sicurezza, performance e stabilità.

### 1. Ambiente e Sicurezza
- Impostare `APP_ENV=production` nel file `.env`.
- Impostare `APP_DEBUG=false` nel file `.env`.
- Assicurarsi che `APP_URL` rifletta il dominio corretto (incluso `https://`).
- Forzare l'utilizzo di HTTPS dal web server (Nginx/Apache) o tramite middleware.

### 2. Integrazioni e credenziali
- Configurare Meta, TikTok e Nextcloud soltanto per i moduli effettivamente usati.
- Configurare `N8N_API_TOKEN` e un distinto `N8N_SIGNING_SECRET`, entrambi
  casuali e di almeno 32 byte.
- In produzione lasciare `N8N_REQUIRE_SIGNATURE=true` e `N8N_REQUIRE_IDEMPOTENCY_KEY=true`.
- Configurare SMTP o il servizio email definitivo.

### 3. Ottimizzazione Prestazioni
Eseguire i comandi di caching forniti da Laravel:
- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`
- `php artisan event:cache`

### 4. Code e Processi in Background
- Configurare Supervisor usando `deploy/supervisor/agency-core.conf.example` come base. Le code social e applicative hanno timeout differenti e devono avere worker dedicati.
- Assicurarsi che il demone cron di sistema esegua il comando di scheduling di Laravel (`* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1`).

### 5. Storage e Permessi
- Verificare i permessi di scrittura sulle directory `storage/` e `bootstrap/cache/`.
- Eseguire `php artisan storage:link` soltanto per loghi e altri asset esplicitamente pubblici. I media social sono salvati sul disco privato `social_media` e vengono consegnati tramite URL firmati.
- Assicurarsi che i driver di storage remoto (es. S3) per i file di Shooting o gli allegati siano correttamente configurati.

### 6. Verifiche prima del traffico
- Eseguire `php artisan migrate --force`.
- Eseguire prima `php artisan social:migrate-media-to-private`, poi lo stesso comando con `--execute` dopo aver verificato il conteggio.
- Avviare scheduler e worker, attendere almeno un ciclo e verificare `php artisan monitor:system`.
- Eseguire `php artisan social:production-readiness --allow-auto-disabled`.
- Eseguire `php artisan optimize` e `php artisan queue:restart` dopo ogni rilascio.
