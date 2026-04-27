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
- **Pagamenti e Booking**: Integrazione sicura (Zero-Trust) con Stripe e PayPal per i checkout pubblici, con validazione server-side degli importi e gestione idempotente delle transazioni (PaymentConfirmationService).

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

### 2. Integrazioni e API Keys
- Aggiornare le credenziali di Stripe passando dalle chiavi di test alle chiavi Live (`STRIPE_KEY`, `STRIPE_SECRET`).
- Configurare i Webhook Secret di Stripe per la ricezione sicura degli eventi.
- Aggiornare le credenziali di PayPal per l'ambiente Live.
- (Opzionale) Configurare le credenziali SMTP o i servizi di invio email definitivi.

### 3. Ottimizzazione Prestazioni
Eseguire i comandi di caching forniti da Laravel:
- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`
- `php artisan event:cache`

### 4. Code e Processi in Background
- Configurare un process monitor (es. Supervisor) per mantenere attivi i worker delle code (`php artisan queue:work`).
- Assicurarsi che il demone cron di sistema esegua il comando di scheduling di Laravel (`* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1`).

### 5. Storage e Permessi
- Verificare i permessi di scrittura sulle directory `storage/` e `bootstrap/cache/`.
- Eseguire `php artisan storage:link` per rendere pubblici gli asset salvati nello storage locale.
- Assicurarsi che i driver di storage remoto (es. S3) per i file di Shooting o gli allegati siano correttamente configurati.
