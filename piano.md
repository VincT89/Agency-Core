Piano completo di consolidamento

Questo piano deriva esclusivamente da:

codice PHP eseguibile;
route;
componenti Livewire;
modelli e policy;
action, job, observer e command;
migrazioni;
test presenti.

Non considero docs/ come fonte del comportamento.

Il flusso applicativo rimane invariato:

Laravel
├── utenti, clienti, progetti, task, ticket
├── fatture, pagamenti e spese
├── campagne e versioni dei post
├── revisione e approvazione
├── account social e token
└── pubblicazione effettiva Meta/TikTok

n8n
├── generazione iniziale dei post
├── rigenerazione caption/immagini
├── chatbot cliente
└── integrazioni già previste

Non verranno spostate:

la generazione da n8n a Laravel;
la pubblicazione da Laravel a n8n;
le responsabilità tra i due sistemi.

L’obiettivo è rendere affidabile il flusso esistente.

Stato della verifica
Tutti i 545 file PHP controllati superano php -l.
Lo ZIP contiene circa 70 file di test.
Non ho potuto eseguire PHPUnit perché lo ZIP non contiene vendor e nell’ambiente non è disponibile Composer.
Alcune verifiche contro Meta, TikTok, Nextcloud e n8n richiederanno necessariamente ambienti reali o sandbox.
I punti indicati come “confermati” derivano direttamente da percorsi presenti nel codice.
I punti indicati come “gate di verifica” non devono essere modificati finché un test o un payload reale non conferma il comportamento.
Principi di implementazione

Per non rompere il progetto, tutte le modifiche devono seguire queste regole.

1. Migrazioni additive

Inizialmente si aggiungono:

nuove colonne nullable;
nuove relazioni;
indici;
snapshot;
tabelle di appoggio.

Non si eliminano subito:

colonne legacy;
valori degli enum;
route;
payload n8n;
relazioni usate dalle view;
campi image_url e image_urls.
2. Dual read e dual write

Per le modifiche più rischiose:

prima:
scrittura vecchia + scrittura nuova
lettura nuova con fallback vecchio

poi:
lettura solo nuova

infine:
rimozione del vecchio codice

Questo vale soprattutto per:

media delle versioni social;
pubblicazioni;
URL Nextcloud;
stati;
informazioni finanziarie.
3. Test di caratterizzazione prima dei cambiamenti

Ogni comportamento attuale deve essere coperto da test prima del refactoring.

Un test di caratterizzazione non dice necessariamente che il comportamento attuale sia corretto. Serve a evitare modifiche accidentali non previste.

4. Action per le mutazioni critiche

Le modifiche a più record devono passare da action transazionali, non rimanere nei controller o nei componenti Livewire.

Le action devono controllare:

autorizzazione o precondizioni;
stato attuale;
lock;
transazione;
audit;
eventi;
job afterCommit().
5. Nessun job dipendente dalla sessione web

Command, scheduler e queue devono usare query esplicitamente definite per i processi di sistema.

Non devono dipendere da:

Auth::user()

o dagli scope pensati per le richieste web.

Fase 0 — Baseline, test e sicurezza del rilascio

Questa fase precede qualsiasi correzione funzionale.

0.1 Ripristinare un ambiente eseguibile

Preparare:

.env.example;
database di test;
servizi fake per n8n, Meta, TikTok e Nextcloud;
Redis o database queue per i test;
configurazione filesystem di test.

Lo ZIP non contiene .env.example. Va creato partendo esclusivamente dalle variabili realmente lette da config/ e dal codice.

Non devono essere inseriti segreti reali.

0.2 Pulire il pacchetto del progetto

Lo ZIP contiene:

storage/logs/laravel.log di circa 41 MB;
file temporanei Livewire;
cache e altri artefatti runtime.

Creare uno script di packaging che escluda:

.env
vendor/
node_modules/
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
storage/app/private/livewire-tmp/*
database/database.sqlite
public/build, se rigenerabile
0.3 Pipeline CI iniziale

La prima pipeline deve eseguire:

composer install
php -l sui file applicativi
php artisan migrate:fresh --env=testing
php artisan test
npm ci
npm run build

Analisi statica più severa può essere aggiunta dopo aver creato una baseline. Non deve bloccare le prime correzioni se produce centinaia di segnalazioni legacy.

0.4 Test end-to-end di caratterizzazione

Creare scenari completi per:

autenticazione e disattivazione utente;
cliente e directory Nextcloud;
progetto e assegnazione membri;
task e ticket;
shooting;
fattura e pagamenti;
campagna, post, n8n, revisione e pubblicazione;
scheduler;
chatbot.
0.5 Backup e rollback

Prima di ogni migrazione:

backup database;
inventario dei record coinvolti;
command di analisi in modalità --dry-run;
migrazione additive;
verifica;
solo successivamente eventuale backfill.
Fase 1 — Query di sistema, scheduler e processi in background

Questa è una priorità P0 perché alcuni processi pianificati possono attualmente lavorare su zero record senza mostrare errori.

1.1 Correggere gli scope nei command
Problema confermato

ProjectSupremacyScope applica:

whereRaw('1 = 0')

quando non esiste un utente autenticato.

Questo comportamento è appropriato per una query web “fail closed”, ma non per scheduler e queue.

NotifyDueTasks usa:

Task::query()

Nel comando non esiste una sessione autenticata, quindi può ottenere zero task.

NotifyOverdueInvoices presenta lo stesso problema con:

Invoice::query()
Invoice::where(...)

Esistono già:

TaskQuery::forSystemBatch()
InvoiceQuery::forSystemDetection()

ma i command non le usano.

Implementazione

Modificare:

NotifyDueTasks
→ TaskQuery::forSystemBatch()

NotifyOverdueInvoices
→ InvoiceQuery::forSystemDetection()

Non usare genericamente withoutGlobalScopes(). Rimuovere soltanto ProjectSupremacyScope.

NotifyDueTickets usa già esplicitamente il bypass corretto.

Test

Eseguire i command senza utente autenticato e verificare che:

trovino i record;
non notifichino record fuori dai criteri temporali;
non producano duplicati giornalieri;
gli utenti inattivi non ricevano notifiche.
1.2 Convenzione unica per le query di sistema

Creare query esplicite per tutti i processi batch:

TaskQuery::forSystemBatch()
TicketQuery::forSystemBatch()
InvoiceQuery::forSystemDetection()

Ogni nuovo command dovrà usare queste query.

Aggiungere un test architetturale o una ricerca CI che segnali l’uso diretto di modelli con ProjectSupremacyScope dentro app/Console e app/Jobs, salvo eccezioni dichiarate.

1.3 Dispatch degli observer dopo il commit

Diversi observer avviano sincronizzazioni o attività esterne durante il salvataggio dei modelli.

Il rischio è che il job:

parta prima del commit;
legga dati non ancora completi;
parta anche se la transazione viene annullata.

Modificare gli observer che dispatchano job affinché usino:

Job::dispatch(...)->afterCommit();

In particolare controllare:

ClientObserver;
ProjectObserver;
TicketObserver;
MarketingCampaignObserver;
MarketingCampaignPostObserver;
observer collegati alla sincronizzazione chatbot.

Gli audit locali possono restare sincroni se non dipendono da sistemi esterni.

1.4 Monitoraggio operativo

Aggiungere:

heartbeat dello scheduler;
monitoraggio di failed_jobs;
conteggio job pendenti per queue;
allarme per job social bloccati;
allarme per job chatbot falliti;
controllo dell’ultimo completamento dei command giornalieri.

Queue da gestire separatamente secondo quelle realmente usate dal codice:

default
chatbot
social-publishing
Fase 2 — Utenti, autenticazione e conservazione dei dati
2.1 Sostituire la cancellazione ordinaria con la disattivazione
Problema confermato

Gli utenti possono essere eliminati da:

amministrazione;
pagina profilo.

Numerose foreign key usano cascadeOnDelete(). La cancellazione può quindi rimuovere dati storici relativi a:

task;
fatture;
pagamenti;
eventi;
spese;
pivot di progetti e team;
allegati.

Inoltre, una cancellazione tramite cascade database non attiva necessariamente l’evento Eloquent deleting di Attachment, lasciando potenzialmente il file nello storage.

Implementazione compatibile

Prima fase:

mantenere la route esistente;
sostituire internamente l’eliminazione con status = inactive;
cambiare il testo UI da “Elimina” a “Disattiva”;
registrare l’azione nell’audit.

Seconda fase:

impedire il login agli inattivi;
introdurre middleware per bloccare anche sessioni già aperte;
revocare token e sessioni;
eseguire logout forzato.

Terza fase:

mantenere un comando amministrativo separato per la cancellazione fisica;
consentirlo solo dopo una verifica delle dipendenze;
non esporlo nell’interfaccia ordinaria.
2.2 Middleware per utenti inattivi

Il login controlla già lo stato, ma un utente disattivato dopo il login può mantenere la sessione.

Aggiungere middleware autenticato:

se status != active
→ invalidazione sessione
→ rigenerazione token CSRF
→ logout
→ redirect al login

Applicarlo a tutte le route autenticate.

2.3 Migrare gradualmente le foreign key storiche

Non cambiare tutte le foreign key in un’unica release.

Per campi storici come:

created_by
uploaded_by

valutare migrazioni verso:

nullable()->nullOnDelete()

Prima del cambiamento:

verificare i dati;
aggiornare relazioni e view per gestire null;
mostrare “Utente rimosso” quando necessario.

Le relazioni operative, come un assegnatario attuale, possono mantenere regole più restrittive.

2.4 Correggere il disallineamento sulle autorizzazioni progetto
Problema confermato

ProjectPolicy::create() ammette il ruolo Administration, mentre StoreProjectRequest::authorize() usa canManageSystem(), più restrittivo.

La pagina di creazione può risultare accessibile, ma il submit può fallire.

Implementazione

StoreProjectRequest::authorize() deve delegare alla policy:

return $this->user()->can('create', Project::class);

Applicare la stessa regola alle altre FormRequest: non duplicare manualmente la logica dei ruoli.

Fase 3 — Autorizzazioni e isolamento delle risorse
3.1 Correggere le FormRequest che autorizzano sempre
Problemi confermati

Restituiscono true senza un controllo sostitutivo completo:

StoreTicketRequest;
StoreCalendarEventRequest;
StoreHostingServiceInterventionRequest.

I relativi controller non applicano sempre un’autorizzazione esplicita sulla creazione.

Implementazione

Per il ticket:

return $this->user()->can('create', Ticket::class);

Per l’evento:

return $this->user()->can('create', CalendarEvent::class);

Per l’intervento hosting, autorizzare l’aggiornamento del servizio padre.

Nei controller aggiungere anche:

$this->authorize(...)

come protezione esplicita.

3.2 Scope delle risorse figlie

Ogni ID ricevuto da Livewire o da route deve essere risolto attraverso il parent.

Usare:

$post->mediaItems()->findOrFail($id);
$post->publications()->findOrFail($id);
$shoot->proposedSlots()->findOrFail($id);
$invoice->items()->findOrFail($id);
$ticket->comments()->findOrFail($id);
$task->checklistItems()->findOrFail($id);

Evitare:

Model::find($id)

seguito da controlli successivi.

Questo va applicato soprattutto a:

riordinamento media social;
sincronizzazione campi media legacy;
retry e force-fail delle pubblicazioni;
selezione slot shooting.
3.3 Nextcloud autorizzato per risorsa

Attualmente preview e download verificano principalmente che il percorso appartenga alle radici configurate.

Questo non dimostra che l’utente abbia diritto allo specifico cliente o progetto.

Implementazione incrementale

Aggiungere endpoint che riceva:

tipo risorsa
ID risorsa
identificativo file

Il controller deve:

caricare la risorsa;
applicare la policy;
verificare che il file appartenga alla directory registrata;
eseguire preview o download.

Mantenere temporaneamente le route basate sul percorso dietro configurazione e registrarne l’utilizzo, poi migrare le view.

Fase 4 — Clienti
4.1 Uniformare l’unicità tra creazione e modifica
Problema confermato

StoreClientRequest controlla alcuni campi univoci, ma UpdateClientRequest non applica regole equivalenti per:

email;
partita IVA.

Il database non garantisce sempre la stessa unicità.

Implementazione
creare command di analisi duplicati;
risolvere i duplicati esistenti;
uniformare Store e Update;
aggiungere eventualmente indici database dopo la bonifica;
gestire comunque l’eccezione di vincolo per richieste concorrenti.
4.2 Rendere atomica la creazione cliente
Problema confermato

CreateClientAction può:

salvare il logo;
creare la directory Nextcloud;
fallire durante la creazione del cliente.

Possono restare logo o directory orfani.

Implementazione

Separare:

transazione database
operazioni storage
operazioni Nextcloud

Flusso consigliato, mantenendo lo stesso risultato:

validazione;
salvataggio temporaneo del logo;
transazione database cliente;
commit;
creazione directory Nextcloud con job afterCommit;
aggiornamento stato sincronizzazione;
in caso di errore storage, compensazione.

Non far fallire retroattivamente il cliente se Nextcloud è momentaneamente indisponibile. Salvare uno stato di errore e consentire il retry.

4.3 Aggiornamento logo senza file orfani

Durante l’update:

salvare il nuovo logo in percorso temporaneo;
aggiornare il cliente;
promuovere il nuovo logo;
eliminare il vecchio solo dopo il successo;
rimuovere il temporaneo in caso di errore.
4.4 Archiviazione cliente

La cancellazione fisica di un cliente può propagarsi a progetti, fatture, campagne e altri dati.

Il comportamento ordinario deve diventare:

active
inactive
archived

La cancellazione fisica deve essere bloccata quando esistono dati aziendali collegati.

Fase 5 — Progetti e team
5.1 Correggere withTrashed() sui progetti
Errore confermato

ProjectController::uniqueSlug() usa:

Project::withTrashed()

ma Project non usa SoftDeletes.

Implementazione

Usare:

Project::query()

Non introdurre SoftDeletes solo per adattare questo metodo.

Testare:

primo slug;
nome duplicato;
aggiornamento;
concorrenza sulla creazione.
5.2 Validare codice e stato progetto

Il codice progetto è univoco nel database, ma la validazione applicativa non è equivalente.

Aggiungere:

Rule::unique('projects', 'code')

con ignore() in modifica.

Lo stato deve essere validato contro i valori già usati dal codice:

active
completed
on_hold
cancelled

Non aggiungere nuovi stati.

5.3 Membri attivi

Le view mostrano utenti attivi, ma una richiesta manipolata può inviare utenti inattivi.

Validare:

Rule::exists('users', 'id')->where('status', 'active')

sia nei progetti sia nei team.

5.4 Pivot e storico assegnazioni

Le pivot contengono campi come:

assignment_status
assigned_at / joined_at
unassigned_at

ma sync() elimina le righe rimosse.

Prima di modificare questa parte:

testare quale storico viene realmente mostrato;
verificare se esistono query che usano assignment_status;
verificare i dati presenti.

Se lo storico è effettivamente parte del flusso già implementato, sostituire sync() con aggiornamento differenziale:

nuovo membro → assigned
membro rimosso → unassigned + data
membro riaggiunto → assigned

Se invece quei campi non sono usati da alcun flusso, non introdurre una nuova semantica: mantenere sync() e trattare i campi come debito tecnico da rimuovere in una fase separata.

5.5 Cancellazione progetto

Aggiungere una schermata di dipendenze prima della cancellazione:

task;
ticket;
eventi;
fatture;
allegati;
membri.

Per il consolidamento, preferire archiviazione o stato cancelled. La cancellazione definitiva deve essere esplicita e testata.

Fase 6 — Task
6.1 Unificare gli stati esistenti
Problema confermato

TaskController ammette:

todo
in_progress
waiting
done

Altre parti del modello conoscono anche:

review
cancelled
Implementazione

Creare un’unica sorgente per i valori già esistenti:

TaskStatus enum o costante centrale

Usarla in:

Store/Update;
cambio rapido stato;
filtri;
Kanban;
label;
ordinamento;
observer.

Non aggiungere o rinominare stati.

6.2 Supportare i task senza progetto già creati dallo shooting
Problema confermato

La migrazione consente project_id = null e il flusso shooting può creare task senza progetto.

Il CRUD ordinario richiede sempre project_id.

Implementazione

Rendere project_id nullable nelle FormRequest.

Per i task senza progetto, mantenere l’accesso previsto dallo scope attuale:

assegnatario diretto

Aggiungere test che un task creato da uno shooting marketing possa:

essere visualizzato;
essere modificato;
cambiare stato;
ricevere commenti e checklist.
6.3 Uniformare la visibilità

ProjectSupremacyScope permette task senza progetto assegnati all’utente, mentre Task::scopeVisibleTo() non applica la stessa eccezione.

Creare una query unica e richiamarla da entrambi.

6.4 Validare assegnatario e progetto

Per task con progetto:

assegnatario attivo;
assegnatario membro del progetto.

Per task senza progetto:

assegnatario attivo.

La policy non deve creare il caso in cui un utente sia assegnatario ma non possa vedere il task.

6.5 Coerenza ticket-task-progetto

Quando esiste ticket_id:

il ticket deve appartenere allo stesso progetto;
oppure entrambi devono essere senza progetto, se già supportato dal flusso.

Non consentire riferimenti incrociati tra progetti.

6.6 Centralizzare le transizioni

Creare action per:

creazione;
aggiornamento;
assegnazione;
cambio stato.

Centralizzare anche completed_at:

status → done
→ completed_at = now()

uscita da done
→ completed_at = null
Fase 7 — Ticket
7.1 Correggere l’autorizzazione di creazione

Applicare la correzione di StoreTicketRequest e aggiungere autorizzazione nel controller.

7.2 Visibilità ticket senza progetto

n8n può creare ticket senza progetto attraverso CreateTicketFromN8n.

Il codice web non tratta in modo uniforme questi record:

scope globale;
policy;
scopeVisibleTo();
ruolo Marketing.

Prima del cambiamento, creare test per il comportamento atteso già deducibile dalle assegnazioni:

amministratori;
assegnatario diretto;
creatore;
utenti progetto;
Marketing.

Poi implementare una sola query condivisa.

Non esporre automaticamente tutti i ticket senza progetto a tutti gli utenti.

7.3 Assignee attivo e coerente

Il form deve mostrare e accettare utenti attivi.

Per ticket con progetto, l’assegnatario deve appartenere al progetto, salvo ruoli globali già previsti.

7.4 Commenti verso Sody/n8n
Problema di affidabilità

TicketComments crea il commento e chiama n8n sincronicamente durante l’azione Livewire.

Consolidamento

Mantenere esattamente gli stati esistenti:

pending
processing
sent
failed

Ma spostare l’invio in:

SendTicketCommentToN8nJob

Flusso:

transazione commento;
idempotency_key;
dispatch afterCommit;
n8n;
callback aggiorna lo stato;
retry usa lo stesso commento e una chiave controllata.

L’interfaccia non deve restare bloccata dalla latenza di n8n.

7.5 Idempotenza messaggi cliente

N8nChatbotController::store() non richiede un ID univoco del messaggio in ingresso.

Lo stesso payload reinviato può creare commenti duplicati e ripetere una transizione.

Aggiungere, senza cambiare la struttura funzionale:

external_message_id

e una tabella o indice di deduplicazione:

integration + external_message_id

Durante la transizione, renderlo prima facoltativo con logging; successivamente obbligatorio quando n8n sarà aggiornato.

Fase 8 — Calendario
8.1 Autorizzazione creazione

Correggere StoreCalendarEventRequest e applicare la policy nel controller.

8.2 Intervalli sovrapposti
Errore confermato

Il filtro attuale considera principalmente gli eventi il cui start_at cade nella finestra.

Un evento iniziato prima, ma ancora in corso, può non essere restituito.

Usare la condizione di sovrapposizione:

start_at < fine intervallo
AND
end_at > inizio intervallo

Gestire separatamente gli eventuali eventi senza durata in base ai dati già presenti.

8.3 Validazione temporale

Applicare sempre:

end_at >= start_at

anche agli aggiornamenti drag-and-drop.

8.4 Assegnatari

Per un evento collegato a progetto:

assegnatario attivo;
assegnatario con accesso al progetto.

Per eventi personali, mantenere l’assegnazione automatica all’utente corrente.

Fase 9 — Shooting

Il flusso da mantenere è:

richiesta
→ attesa fotografo
→ accettazione/rifiuto
→ attesa cliente
→ conferma
→ creazione task ed evento
→ programmato
9.1 Correggere relazioni inesistenti
Errori confermati

Sono chiamate relazioni non presenti:

$user->clients()

e:

whereHas('client.users')

Esiste invece il percorso reale:

utente
→ progetti
→ cliente
→ campagna

UserScopedShootScope contiene già una query coerente per parte del flusso.

Implementazione

Estrarre una query di accesso shooting e riutilizzarla in:

ShootPolicy;
CreateRequest;
MarketingCampaignCalendar;
liste e dettaglio shooting.

Non aggiungere una relazione diretta User-Client che non esiste nel modello dati.

9.2 Progetto o campagna, non entrambi

Le regole attuali consentono potenzialmente entrambi.

Applicare:

project_id richiesto se manca campaign
marketing_campaign_id richiesto se manca project
ognuno proibisce l’altro

Ripetere il controllo nell’action.

9.3 Fotografo attivo con ruolo corretto

Validare:

esistenza;
status = active;
ruolo fotografo.
9.4 Validazione slot

period deve usare i valori già definiti da ShootSlotPeriod.

Dopo la normalizzazione deve restare almeno uno slot valido.

9.5 Slot appartenente allo shooting

PhotographerRespondAction deve caricare lo slot tramite:

$shoot->proposedSlots()

e non tramite ID globale.

9.6 Concorrenza e idempotenza

Dentro transazioni con lockForUpdate():

Risposta fotografo
ricontrollare lo stato;
verificare lo slot;
impedire doppia risposta;
aggiornare tutti gli slot coerentemente;
notificare dopo il commit.
Conferma cliente
ricontrollare stato e slot;
impedire doppia conferma;
se task_id o calendar_event_id esistono, non ricrearli;
creare task ed evento una volta sola;
notificare dopo commit.
9.7 Test essenziali
shooting progetto;
shooting campagna;
utente non globale;
fotografo inattivo;
slot di altro shooting;
doppia accettazione;
doppia conferma;
task senza progetto risultante.
Fase 10 — Fatture e pagamenti
10.1 Una sola sorgente per i totali
Problema confermato

CreateInvoiceAction accetta subtotal dal payload.

InvoiceController::update() aggiorna il totale prima delle righe e non lo ricalcola necessariamente dopo le modifiche.

Implementazione

Creare:

InvoiceTotalsCalculator

Usando soltanto la formula già presente:

subtotal = somma invoice_items.total
total = subtotal + tax_amount

Non introdurre sconti o nuove componenti.

Flusso create/update:

transazione;
creazione o modifica righe;
ricalcolo subtotal;
ricalcolo total;
salvataggio fattura;
commit.

Mantenere temporaneamente i campi nel form, ma ignorare i valori derivabili dal client. Successivamente renderli non modificabili nella UI.

10.2 paid_total derivato dai pagamenti
Problema confermato

paid_total può essere inviato dal form, ma viene anche sincronizzato dai record Payment.

Deve esistere una sola sorgente:

somma dei pagamenti

Rimuovere progressivamente paid_total dai campi modificabili.

Aggiungere command:

invoices:check-payment-totals --dry-run
invoices:repair-payment-totals
10.3 Pagamenti concorrenti
Problema confermato

La validazione del residuo avviene prima del lock della fattura.

Due richieste contemporanee possono superare entrambe il controllo.

Creare action:

RegisterPaymentAction
UpdatePaymentAction
DeletePaymentAction

Ogni action:

blocca la fattura;
calcola il pagato effettivo;
valida il nuovo importo;
salva o elimina il pagamento;
sincronizza paid_total;
sincronizza lo stato;
commit.
10.4 Stati fattura

Non eliminare gli stati esistenti:

draft
issued
partially_paid
paid
overdue
cancelled

Centralizzare però la sincronizzazione:

draft, issued, cancelled rimangono transizioni gestite;
partially_paid, paid, overdue devono essere coerenti con pagamenti e scadenza.

Prima di cambiare il comportamento di una fattura parzialmente pagata e scaduta, bloccare quello attuale con test. Il codice non esprime una sola regola abbastanza chiara per introdurne una diversa senza conferma.

10.5 Filtro cliente
Errore confermato

InvoiceQuery::forIndex() filtra il cliente passando dal progetto.

Le fatture hanno già client_id e possono appartenere a campagne senza progetto.

Usare:

where('client_id', ...)
10.6 Serie temporali finanziarie
Errore confermato

Alcuni metodi ricevono $months ma lo sovrascrivono a 12 e usano l’inizio dell’anno.

Correggere affinché restituiscano esattamente il numero richiesto di mesi.

10.7 Date del riepilogo economico

Validare:

from = data valida
to = data valida e >= from

Normalizzare le date nello stesso timezone applicativo.

10.8 Fatture collegate a periodi ed extra delle campagne
Problema confermato

Periodi ed extra hanno invoice_id con nullOnDelete.

Se la fattura viene eliminata:

invoice_id torna null;
lo stato può restare invoiced.

La risorsa risulta fatturata ma senza fattura.

Prima correzione conservativa

Impedire la cancellazione di una fattura che contiene InvoiceItem con billable_type non nullo.

Mostrare un messaggio esplicito.

Una futura azione di annullamento può ripristinare:

periodo a planned;
extra a pending;

ma non va introdotta implicitamente dentro destroy() senza uno specifico flusso UI.

Fase 11 — Campagne marketing, periodi ed extra

Questa fase mantiene gli attuali lifecycle e l’attuale fatturazione.

11.1 Rendere atomiche estensione e rinnovo

ExtendMarketingCampaignAction e RenewMarketingCampaignAction eseguono più update e creazioni senza transazione unica.

Racchiudere in transazione:

aggiornamento campagna
creazione periodo
aggiornamento date
commit

Non introdurre regole nuove sui periodi sovrapposti: il codice attuale non ne esprime una.

11.2 Validazione completa modifica campagna

saveCampaign() valida principalmente nome e canone.

Validare anche i campi già presenti:

stato contro MarketingCampaignStatus;
date;
ends_at >= starts_at;
cliente accessibile;
note e descrizione.

UpdateMarketingCampaignAction usa tryFrom() sullo stato. Un valore non valido non deve arrivare all’action.

11.3 Extra

Il flusso attuale è:

pending
invoiced
cancelled

Mantenere questi stati.

L’annullamento già impedisce l’operazione quando esiste invoice_id. Aggiungere transazione/lock per evitare che fatturazione e annullamento avvengano contemporaneamente.

11.4 Generazione fattura campagna

GenerateMarketingCampaignInvoiceAction è già transazionale e blocca periodi ed extra.

Consolidare:

verificare che tutti gli ID richiesti appartengano alla campagna;
verificare che non siano già fatturati;
impedire richieste parzialmente silenziose quando alcuni ID non sono più validi;
mantenere righe personalizzate e formula attuale;
usare lo stesso InvoiceTotalsCalculator del modulo fatture.
11.5 Coerenza dopo cancellazione fattura

Applicare il blocco descritto nella sezione fatture.

Fase 12 — Spese
12.1 Coerenza tra policy e lista
Problema confermato

ExpensePolicy consente agli utenti finance di vedere tutte le spese.

ExpensesIndex applica sempre:

ownedBy(auth()->id())

Quindi una spesa può essere accessibile via URL ma non comparire nell’elenco.

Implementazione

Usare una query centrale coerente con la policy.

Poiché la policy attuale concede accesso finance globale, l’elenco finance deve mostrare tutte le spese. Non modificare la policy senza una decisione separata.

12.2 Filtro hosting

Il form supporta hosting_service, ma la mappa del filtro dell’indice non lo include.

Aggiungerlo alla mappa esistente.

12.3 Route della risorsa collegata
Errore confermato

La view costruisce la route con:

strtolower(class_basename(...)) . 's.show'

Per HostingService produce un nome diverso da hosting-services.show.

Usare un mapping esplicito per i tipi già supportati.

12.4 Validazione delle date

Applicare:

due_date >= expense_date
12.5 Mutazioni autorizzate

ExpenseShow autorizza nel mount(), poi modifica direttamente lo stato.

Aggiungere autorizzazione anche in ogni metodo:

markAsPaid;
markAsPending;
markAsCancelled.

Estrarre un’action che mantenga coerente paid_at.

12.6 Collegamenti accessibili

Il form costruisce liste differenti per cliente, progetto, ticket, task e hosting.

Centralizzare la risoluzione delle risorse accessibili e non mostrare tutti i servizi hosting a utenti non globali senza una policy equivalente.

Non definire una nuova regola di appartenenza hosting: usare la relazione con cliente e le policy esistenti dopo averle rese coerenti.

Fase 13 — Hosting
13.1 Autorizzazioni interventi

Correggere FormRequest e controller come già indicato.

L’intervento deve essere creato o eliminato solo da chi può aggiornare il servizio hosting.

13.2 last_intervention_at
Problemi confermati

Durante la creazione, viene impostata la data del nuovo intervento anche se è più vecchia dell’attuale.

Durante la cancellazione non viene ricalcolata.

Implementazione

Dopo ogni creazione o eliminazione:

MAX(intervention_at)

Aggiornare last_intervention_at con quel valore.

Creazione e cancellazione devono usare action transazionali.

13.3 Accesso password

La password usa un cast cifrato e l’endpoint applica una policy dedicata.

Consolidare aggiungendo audit per:

utente
servizio
timestamp
IP
azione di visualizzazione

Non salvare mai la password o sue parti nei log.

Fase 14 — Allegati
14.1 MIME server-side
Problema confermato

AttachmentController salva:

$file->getClientMimeType()

che deriva dal client.

Salvare invece il MIME rilevato dal server:

$file->getMimeType()

Conservare:

nome originale;
estensione originale;
dimensione reale;
MIME server-side;
checksum SHA-256.
14.2 Ciclo di vita dei file

Attachment elimina il file nel proprio evento Eloquent deleting.

Se il record viene eliminato tramite cascade database, tale evento potrebbe non essere eseguito.

Implementazione

Creare:

DeleteAttachmentAction
DeleteAttachableAttachmentsAction

Le action di eliminazione dei parent devono eliminare gli allegati esplicitamente.

Aggiungere command:

attachments:scan-orphans --dry-run

Prima release:

solo report;
nessuna cancellazione automatica.

Seconda release:

quarantena;
cancellazione dopo un periodo configurato.
14.3 Policy allegati

La policy attuale consente:

download secondo diritto di view sul parent;
eliminazione all’autore con view;
eccezione finance per fatture e pagamenti;
bypass ruoli globali.

Mantenere questa semantica e aggiungere test per ogni tipo presente in ATTACHABLE_MAP.

Fase 15 — Nextcloud
15.1 Conservare i metadati disponibili
Problema confermato nel flusso media social

La selezione Nextcloud riceve:

dimensione;
MIME;
file ID;

ma alcuni passaggi conservano soltanto nome e percorso.

Persistono quindi record con:

mime_type = null
nextcloud_file_id = null
Implementazione

Creare un mapper unico:

NextcloudFileData

con:

file_id
path
original_name
mime_type
file_size
share_url

Usarlo in creazione e modifica.

15.2 Download e preview

Aggiungere:

policy sulla risorsa proprietaria;
stream senza caricare interamente file grandi in memoria;
gestione file mancanti;
timeout;
log senza credenziali;
test per nomi speciali e caratteri Unicode.
15.3 Directory cliente

Spostare creazione e aggiornamento directory in job afterCommit, con stato di sincronizzazione e retry.

Fase 16 — Flusso social completo

Il flusso resta:

Laravel prepara il post
→ Laravel invia a n8n
→ n8n genera o rigenera
→ n8n richiama Laravel
→ Laravel crea una versione
→ revisione cliente
→ approvazione interna
→ Laravel pubblica su Meta/TikTok

L’obiettivo centrale è garantire:

versione generata
=
versione mostrata
=
versione approvata
=
versione pubblicata
16.1 Collegare media e versioni
Problema confermato

La callback n8n salva gli URL generati in MarketingCampaignPostVersion:

image_url
image_urls
image_path = null

I publisher Meta e TikTok leggono invece:

$post->orderedMediaItems

ossia i media del post.

È possibile mostrare una foto generata e pubblicare quella originale.

Migrazione additive

Creare una relazione versione-media, ad esempio:

marketing_campaign_post_version_media
├── id
├── marketing_campaign_post_version_id
├── marketing_campaign_post_media_id
├── sort_order
└── timestamps

Non rimuovere gli attuali media del post.

Flusso callback
ricezione URL da n8n;
download controllato;
salvataggio nel filesystem Laravel;
creazione di MarketingCampaignPostMedia;
associazione alla nuova versione;
aggiornamento current_version_id;
stato Generated.
Comportamento delle rigenerazioni

Usare esattamente i tipi esistenti:

rigenerazione caption: copia i media dalla versione precedente;
rigenerazione immagine: nuovi media, testo precedente;
rigenerazione completa: nuovi media e nuovo testo.
16.2 Download sicuro dei risultati n8n

Il servizio di download deve:

consentire solo HTTP/HTTPS;
bloccare localhost;
bloccare reti private e link-local;
limitare redirect;
limitare byte;
verificare MIME reale;
verificare che il file sia decodificabile;
calcolare checksum;
assegnare nome interno;
eliminare il parziale in caso di errore.

Non usare gli URL esterni come sorgente permanente.

16.3 Versioni immutabili
Problema confermato

MarketingCampaignPostShow::savePost() modifica direttamente la versione corrente.

Inoltre riscrive image_url e image_urls sulla base dei media correnti del post.

Implementazione

Quando esiste una versione corrente:

non eseguire currentVersion->update();
creare una nuova versione manuale con CreateManualMarketingCampaignPostVersionAction;
copiare i media associati;
applicare le modifiche;
aggiornare current_version_id.

Le versioni precedenti devono restare invariate.

16.4 Stato non modificabile dal form
Problema confermato

I form accettano qualunque valore di MarketingCampaignPostStatus.

Rimuovere status dai campi salvabili ordinari.

Lo stato deve cambiare soltanto attraverso action:

SubmitMarketingCampaignPostToN8nAction
RequestMarketingCampaignPostRegenerationAction
callback n8n
SendMarketingCampaignPostToClientAction
approvazione cliente
approvazione interna
PublishMarketingCampaignPostAction
callback/polling publisher
16.5 Guard centrale degli stati

Creare:

MarketingCampaignPostStateGuard

Usando esclusivamente gli stati già presenti.

Metodi:

canEdit
canSubmit
canRegenerate
canCancelGeneration
canSendToClient
canApprove
canPublish

Le view devono usare lo stesso guard delle action.

16.6 Rigenerazione transazionale

RequestMarketingCampaignPostRegenerationAction deve:

bloccare il post;
ricontrollare lo stato;
salvare request ID;
salvare stato precedente;
creare commento;
commit;
dispatch afterCommit.

Due richieste concorrenti devono produrre una sola rigenerazione attiva.

16.7 Cancellazione e callback tardiva

Creare action di cancellazione che:

blocca il post;
verifica il request ID;
aggiorna stato e contesto;
invalida la richiesta corrente;
pulisce asset temporanei.

La callback deve controllare che il request ID sia ancora quello attivo.

Una callback tardiva non deve creare una nuova versione dopo la cancellazione.

16.8 Idempotenza dell’ID di generazione
Problema confermato

La ricerca di external_generation_id è globale.

Se trova una versione di un altro post, può restituirla come risultato idempotente.

Implementare:

stesso external_generation_id + stesso post
→ idempotente

stesso external_generation_id + post diverso
→ conflitto

Aggiungere un vincolo database solo dopo aver verificato, attraverso payload reali, se l’ID n8n è globalmente unico o unico per post.

16.9 Revisione cliente legata alla versione
Problema confermato

Il token conserva version_number, ma la pagina pubblica usa la versione corrente del post.

Una rigenerazione successiva può far approvare al vecchio link una versione diversa.

Implementazione

Salvare nel token:

marketing_campaign_post_version_id

La pagina pubblica deve caricare quella versione.

Commenti e approvazione devono riferirsi a quella versione.

Quando parte una rigenerazione:

invalidare i token attivi;
uscire da SentToClient;
richiedere un nuovo invio dopo il completamento.
16.10 Approvazione transazionale

L’approvazione cliente deve, in una sola transazione:

bloccare token;
verificare scadenza e utilizzo;
bloccare post;
verificare versione;
creare commento;
aggiornare stato;
consumare token.

L’approvazione interna deve usare ApproveMarketingCampaignPostAction e verificare:

stato ammesso;
versione corrente;
media;
nessuna generazione attiva;
piattaforme;
preflight minimo.
16.11 Pubblicazione legata alla versione
Problema confermato

MarketingCampaignPostPublication non contiene marketing_campaign_post_version_id.

payload_snapshot esiste ma non viene valorizzato durante la creazione.

Migrazione

Aggiungere nullable:

marketing_campaign_post_version_id
publication_batch_id, solo se necessario per raggruppare piattaforme

Valorizzare payload_snapshot con:

version_id
caption
hashtag
media ID
path
MIME
checksum
piattaforma
account

Il retry deve usare lo stesso snapshot, anche se il post ha una nuova versione.

16.12 Piattaforme attese

SyncMarketingCampaignPostPublicationStatusAction deve confrontare:

piattaforme richieste;
pubblicazioni create;
risultati finali.

Non deve marcare il post Published se esiste soltanto una pubblicazione riuscita per un sottoinsieme delle piattaforme richieste.

Salvare l’insieme delle piattaforme nel batch o nello snapshot.

16.13 Media Nextcloud nel social

Persistenza obbligatoria di:

mime_type;
file_size;
nextcloud_file_id;
percorso;
nome.

La tabella media non contiene attualmente file_size, mentre i preflight usano anche $media->size o $media->file_size.

Aggiungere una colonna canonica:

file_size

Aggiornare Meta e TikTok a usare la stessa proprietà.

Per i record esistenti:

backfill file locali;
recupero metadati Nextcloud dove disponibile;
report dei record non risolvibili.
16.14 Resolver unico dei media

Creare:

SocialMediaDescriptorResolver

che restituisca:

origine;
MIME;
estensione;
nome;
dimensione;
tipo image/video;
URL temporaneo;
path interno.

MetaPreflight, TikTokPreflight, publisher e payload n8n devono usare lo stesso resolver.

16.15 URL firmati per n8n

MarketingCampaignPostMediaPayloadBuilder deve generare URL temporanei firmati, mantenendo però le stesse chiavi del payload.

Migrazione:

introdurre URL firmati;
n8n continua a ricevere url;
registrare accessi alle route legacy;
rimuovere le route pubbliche solo dopo che non risultano più usate.
16.16 Scoping degli ID Livewire

Correggere:

riordinamento media;
sincronizzazione media legacy;
retry pubblicazioni;
force-fail pubblicazioni.

Ogni record deve essere caricato tramite il post corrente.

16.17 Circuit breaker e rate limit
Problema confermato

SocialCircuitBreaker usa meta come provider predefinito ed è iniettato anche nei flussi TikTok.

Separare:

meta
tiktok
n8n

Il middleware chiamato meta-publishing deve diventare generico o avere chiavi distinte.

16.18 TikTok Photo Mode
Funzionalità confermata come non implementata

TikTokContentPostingService::initializePhotoPost() lancia esplicitamente un’eccezione.

Per consolidare senza introdurre una nuova funzionalità:

bloccare il preflight fotografico TikTok;
non mostrare la combinazione come pubblicabile;
non accodare il job;
mostrare un messaggio esplicito.

L’implementazione della Photo Mode resta un progetto separato.

16.19 Test social di accettazione
Generazione completa
foto A
→ n8n genera B
→ revisione mostra B
→ approvazione riguarda B
→ Meta/TikTok ricevono B
Rigenerazione caption
versione 1: B + testo 1
→ versione 2: B + testo 2
Rigenerazione immagine
versione 2: B + testo 2
→ versione 3: C + testo 2
Vecchio token
token versione 1
→ creazione versione 2
→ token 1 non approva versione 2
Retry
pubblicazione versione 3 fallisce
→ viene creata versione 4
→ retry usa ancora versione 3
Fase 17 — Account social, OAuth e publisher

La struttura Laravel deve restare.

17.1 Credenziali

Mantenere:

token cifrati nel database;
refresh in Laravel;
account e asset in Laravel.

Non trasferirli a n8n.

17.2 Audit delle modifiche sensibili

Registrare senza valori segreti:

connessione account;
disconnessione;
refresh;
revoca;
cambio asset;
fallimento autorizzazione;
stato token.
17.3 Test reali obbligatori

Il codice statico non può certificare:

scope concessi;
redirect URI;
tipo di account;
asset accessibili;
comportamento dei container;
errori reali delle piattaforme.

Creare una matrice di test sandbox/live per:

Meta
Facebook singola immagine;
Facebook più immagini;
Instagram immagine;
Instagram video;
container in elaborazione;
token scaduto;
asset non accessibile;
retry.
TikTok
video supportato;
formato invalido;
token scaduto;
polling;
errore permanente;
Photo Mode bloccato prima della queue.

I test placeholder presenti devono essere completati progressivamente.

Fase 18 — Chatbot e n8n
18.1 Non cambiare i payload in modo distruttivo

Ogni nuovo campo deve essere inizialmente facoltativo:

external_message_id
timestamp
signature
idempotency key

n8n deve poter essere aggiornato prima che Laravel li renda obbligatori.

18.2 Firma delle richieste

N8nAuth usa un Bearer token condiviso.

Migrazione in tre passaggi:

Passaggio 1
Bearer obbligatorio;
firma facoltativa;
logging delle richieste non firmate.
Passaggio 2

n8n invia:

X-Timestamp
X-Signature
Idempotency-Key
Passaggio 3
firma obbligatoria;
finestra temporale;
protezione replay;
token distinti inbound/outbound.
18.3 Log redatti

N8nClient e i controller devono evitare nei log:

token;
callback con segreti;
URL firmati completi;
payload con dati personali non necessari;
risposte complete dei provider.

Conservare request ID, stato, durata e codice errore.

18.4 Sincronizzazione chatbot

Il read model chatbot usa già bypass espliciti degli scope in alcuni punti. Mantenere tale comportamento.

Consolidare:

job afterCommit;
queue dedicata;
retry;
idempotenza;
timestamp ultimo sync;
errore ultimo sync;
comando manuale per singolo cliente.
Fase 19 — Audit log
19.1 Unificare le azioni
Problema confermato

AuditLog::ACTIONS non contiene tutte le stringhe realmente usate.

Nel codice compaiono, tra le altre:

registered_payment
deleted_payment
password_reset

Creare enum o costanti centrali con tutte le azioni esistenti.

Non cambiare subito i valori storici. Aggiungere un mapping compatibile per le stringhe già salvate.

19.2 Filtro dei tipi

AuditLogController costruisce:

App\Models\ + input

Questo non funziona in modo generale per modelli in namespace annidati, come lo shooting.

Usare un mapping esplicito:

ticket → Ticket::class
invoice → Invoice::class
shoot → Shoot::class
...

Non accettare classi arbitrarie dall’input.

19.3 Audit sensibile

Aggiungere eventi per:

disattivazione utente;
cambio ruolo;
reset password;
visualizzazione password hosting;
modifica credenziali social;
approvazione social;
pubblicazione;
annullamento o retry;
cancellazione definitiva.

Non inserire valori sensibili nei payload.

Fase 20 — Notifiche
20.1 Redirect interno

NotificationController usa:

redirect()->to($notification->data['url'])

Attualmente gli URL sono generati internamente, ma il payload database potrebbe essere alterato.

Accettare soltanto:

URL relativi;
URL dello stesso host;
route interne note.

In caso contrario, usare /dashboard.

20.2 Deduplicazione centralizzata

I command implementano controlli simili direttamente sulla tabella notifications.

Estrarre:

NotificationDeduplicator

con chiave:

utente
tipo
risorsa
finestra temporale

Non cambiare la frequenza attuale senza requisito.

20.3 Utenti inattivi

Tutte le risoluzioni destinatari devono filtrare status = active.

Fase 21 — Dashboard

Non emerge un errore strutturale autonomo paragonabile a quelli finanziari o social.

La dashboard dipende però da:

scope;
fatture;
pagamenti;
calendario;
task;
ticket;
hosting.

Dopo le correzioni dei moduli sorgente:

creare fixture con valori noti;
verificare KPI;
verificare ruoli;
verificare che i conteggi amministrativi usino query di sistema;
verificare che quelli operativi rispettino gli scope utente.

Non duplicare formule finanziarie nella dashboard: deve usare FinancialSummaryService.

Fase 22 — Note giornaliere

Il modulo applica query vincolate all’utente corrente per:

note;
entry;
checklist.

Non ho rilevato un bypass statico confermato.

Consolidamento limitato a:

test utente A/B;
validazione della data;
indice univoco user_id + date, se non già presente;
transazione o gestione della concorrenza su firstOrCreate;
limite lunghezza contenuti coerente con il database;
test della pulizia automatica delle entry vuote.

Non serve riscrivere il modulo.

Fase 23 — Checklist task e ticket

I controller caricano gli elementi attraverso task o ticket e applicano le relative autorizzazioni.

Consolidamento:

test di ID appartenente a un’altra risorsa;
ordinamento;
doppio toggle concorrente;
limite lunghezza label;
audit dell’aggiunta, completamento e rimozione, se già previsto nel flusso.

Nessuna modifica architetturale.

Fase 24 — Team

La struttura di base è coerente.

Interventi confermati:

accettare soltanto utenti attivi;
validare i valori dei ruoli pivot;
caratterizzare l’uso di assignment_status e joined_at;
evitare di introdurre uno storico se nessuna parte dell’applicazione lo usa.

Aggiungere test per:

directory visibile;
mutazioni solo admin;
utente inattivo non assegnabile;
conservazione di joined_at durante update.
Fase 25 — Pulizia del codice dopo la stabilizzazione

Questa fase deve avvenire soltanto dopo i test funzionali.

25.1 Estrarre mutazioni dai componenti Livewire grandi

Priorità:

MarketingCampaignPostShow;
MarketingCampaignPostCreate;
MarketingCampaignShow;
componenti shooting;
ExpenseForm.

Livewire deve occuparsi di:

input
validazione UI
chiamata action
messaggio risultato

Non di transazioni e regole di dominio.

25.2 Servizio unico di accesso operativo

Le query cliente/progetto sono replicate in molte parti.

Creare un servizio che centralizzi esclusivamente le relazioni già esistenti:

accessibleProjectIds
accessibleClientIds
canAccessProject
canAccessClient

Policy e scope continueranno a esistere, ma useranno la stessa logica.

25.3 Codice morto

Rimuovere soltanto dopo copertura:

branch n8n non usati nei commenti task;
import inutilizzati;
placeholder media privi di implementazione;
vecchie route media dopo la migrazione;
campi legacy social dopo il periodo dual-read.
Sequenza di rilascio consigliata
Release 1 — Baseline e processi invisibili
ambiente test e CI;
pulizia packaging;
command task/fatture senza scope web;
observer afterCommit;
monitoraggio scheduler e queue;
test di caratterizzazione.
Release 2 — Sicurezza e autorizzazioni
disattivazione utenti;
middleware utenti attivi;
autorizzazioni ticket;
autorizzazioni calendario;
autorizzazioni interventi hosting;
scoping delle risorse figlie;
Nextcloud resource-based.
Release 3 — Core operativo
progetto withTrashed;
codice/stato progetto;
utenti attivi in progetti e team;
stati task;
task senza progetto;
visibilità task/ticket;
calendario;
shooting transazionale.
Release 4 — Finanza
calcolatore fatture;
create/update transazionali;
paid_total derivato;
pagamenti con lock;
filtro cliente;
scheduler overdue;
serie mensili;
blocco cancellazione fatture con billable collegati.
Release 5 — Clienti, spese, hosting e file
unicità clienti;
logo e Nextcloud compensati;
archiviazione cliente;
spese coerenti con policy;
interventi hosting;
allegati e cleanup;
metadati Nextcloud.
Release 6 — Coerenza social
tabella versione-media;
salvataggio risultati n8n;
versioni immutabili;
state guard;
token legati a versione;
approvazioni transazionali;
publication version e snapshot;
piattaforme attese;
media metadata;
circuit breaker distinti.
Release 7 — Integrazioni e hardening
firma n8n;
idempotenza chatbot;
commenti accodati;
audit;
notifiche;
test Meta/TikTok;
dismissione graduale route e campi legacy.
Gate obbligatori prima della produzione
Gate 1 — Dati

I command di verifica devono restituire zero incongruenze per:

subtotal fattura;
total fattura;
paid_total;
stati fattura;
invoice billable senza collegamento;
media social mancanti;
versioni senza media;
pubblicazioni senza versione;
allegati senza file;
file senza allegato;
utenti inattivi ancora autenticati.
Gate 2 — Flussi

Devono passare:

cliente → progetto → task
ticket → commento n8n → callback
shooting → task + calendario
campagna → periodo/extra → fattura
post → n8n → versione → revisione → pubblicazione
Gate 3 — Concorrenza

Testare:

due pagamenti;
due rigenerazioni;
due approvazioni;
cancellazione e callback;
due risposte fotografo;
due conferme shooting;
due retry della stessa pubblicazione.
Gate 4 — Sicurezza

Testare:

ID di altra risorsa;
file Nextcloud non autorizzato;
media social scaduto;
firma n8n errata;
replay webhook;
utente inattivo;
redirect notifica esterno;
allegato MIME falso.
Gate 5 — Provider

Completare test reali o sandbox per:

Meta OAuth e pubblicazione;
TikTok OAuth e video;
Nextcloud;
n8n generazione;
n8n chatbot.
Criterio finale di completamento

Il consolidamento è concluso quando:

nessun flusso cambia responsabilità
nessun campo legacy viene rimosso prima della migrazione
nessuna action critica modifica dati fuori transazione
nessun processo batch dipende dalla sessione web
nessun utente inattivo mantiene l’accesso
nessuna fattura diverge dalle proprie righe e pagamenti
nessun token cliente approva una versione differente
nessuna pubblicazione usa media diversi da quelli approvati
nessun retry cambia implicitamente il contenuto pubblicato
nessun file viene esposto senza autorizzazione o scadenza

La priorità più alta non è il refactoring estetico. È chiudere, nell’ordine:

query di sistema;
autorizzazioni;
conservazione dei dati;
contabilità;
shooting;
coerenza versione-media-approvazione-pubblicazione;
sicurezza delle integrazioni.

Solo dopo questi blocchi conviene ridurre i componenti Livewire e rimuovere il codice legacy.