# Ambito funzionale e limiti

## Scopo

Agency Core è un gestionale interno. Coordina il lavoro dell'agenzia, ma non
sostituisce i pannelli dei provider e non offre un portale cliente generale.

## Moduli operativi

### Clienti e progetti

- anagrafiche, dati fiscali, contatti, stato e referente;
- logo con consegna tramite URL firmato;
- creazione delle cartelle foto e video su Nextcloud quando viene indicato un
  nome cartella;
- progetti, assegnazioni degli utenti e visibilità limitata al perimetro
  autorizzato;
- protezione dei dati social storici da cancellazioni incoerenti.

### Task, ticket e calendario

- creazione, assegnazione, priorità, stato, scadenza e checklist;
- commenti interni e integrazione dei messaggi gestiti da n8n;
- notifiche di assegnazione e scadenza;
- campanella ticket separata dalle notifiche personali;
- eventi di calendario manuali e generati dal flusso shooting.

### Marketing e contenuti

- campagne, periodi, extra, modalità di pubblicazione e calendario editoriale;
- post, versioni, commenti, media e rigenerazione tramite n8n;
- revisione cliente tramite collegamento pubblico firmato e limitato al singolo
  contenuto;
- pubblicazione verso Facebook, Instagram e TikTok tramite code asincrone;
- snapshot immutabile di versione, target e media prima dell'invio;
- controlli di integrità, preflight, classificazione errori, retry e revisione
  manuale.

### Shooting

- richiesta creata dal marketing con fotografo e date proposte;
- risposta del fotografo assegnato;
- contatto del cliente effettuato esternamente e registrato dal marketing;
- conferma o rifiuto del cliente registrati dal marketing;
- generazione automatica di task ed evento calendario dopo la conferma;
- nuova proposta dopo il rifiuto del fotografo o del cliente.

Il cliente non accede al gestionale per questo flusso. L'esecuzione e la
consegna fotografica restano esterne.

### Amministrazione

- fatture gestionali, voci, IVA, pagamenti e scadenze;
- calcolo dei totali a partire dalle righe, senza fidarsi dei totali inviati dal
  browser;
- riepilogo economico e spese;
- dati fiscali dell'agenzia;
- controlli per la preparazione elettronica TD01;
- numero fiscale progressivo per anno e serie;
- snapshot fiscale e blocco delle modifiche dopo la preparazione.

La trasmissione elettronica non è completa: mancano XML FatturaPA, Aruba e
ricezione degli esiti SdI.

### Hosting e operatività

- servizi hosting, domini, credenziali cifrate, rinnovi e interventi;
- note operative personali;
- monitoraggio di scheduler, code e job falliti;
- conservazione limitata dei log operativi e pulizia pianificata.

## Ruoli e visibilità

| Ruolo | Responsabilità principale | Visibilità particolare |
| --- | --- | --- |
| Admin | Gestione completa e configurazione | Globale; unico ruolo con registro attività completo, accessi utenti e connessioni social. |
| Administration | Clienti, progetti e finanza | Globale sui progetti; accesso a fatture, pagamenti, spese e profilo fiscale. |
| Operations Manager | Coordinamento operativo | Globale sui progetti e gestione anagrafiche operative. |
| Marketing | Campagne, contenuti e shooting | Campagne e progetti autorizzati; può vedere il cruscotto operativo social ma non il registro amministrativo. |
| Photographer | Shooting assegnati | Può rispondere soltanto agli shooting assegnati e vedere il contesto necessario. |
| Developer | Sviluppo, ticket, task e hosting | Limitato ai progetti autorizzati. |
| Graphic Designer | Attività creative | Limitato ai progetti autorizzati. |

Le autorizzazioni sono applicate dalle policy. La sola presenza di un link o di
una voce di menu non concede l'accesso alla risorsa.

## Notifiche e audit

- Le notifiche personali sono memorizzate nel database e possono essere lette,
  aperte o eliminate dal solo destinatario.
- Gli shooting producono notifiche contestuali per fotografo, creatore e admin
  in base all'evento.
- La campanella ticket conta i ticket nuovi nel perimetro dell'utente e non va
  confusa con le notifiche generiche.
- Il registro attività traccia accessi, uscite e operazioni rilevanti, filtra i
  campi sensibili e resta accessibile soltanto agli admin.

## Integrazioni

| Integrazione | Stato reale |
| --- | --- |
| Nextcloud | Collegata e testata; cartelle cliente e media sono operative. |
| n8n | Contratto implementato con Bearer, HMAC e idempotenza. |
| Meta | Publisher e OAuth implementati; autorizzazione dell'app e collaudo reale pendenti. |
| TikTok | Codice presente; attivazione reale soggetta a credenziali, modalità e collaudo separati. |
| Aruba Fatturazione Elettronica | Non implementata; esiste soltanto la fondazione fiscale interna. |
| Email | Dipende dal mailer configurato nell'ambiente. |

## Limiti dichiarati

- Nessun portale cliente generale.
- Nessun pagamento online Stripe o PayPal.
- Nessun invio elettronico ad Aruba/SdI allo stato attuale.
- Solo TD01 nella preparazione fiscale corrente.
- Nessuna eliminazione automatica delle cartelle Nextcloud quando si elimina un
  record locale.
- La modifica del nome cartella Nextcloud crea la nuova struttura ma non sposta
  né cancella quella precedente.
- Le autorizzazioni esterne, le quote e i cambi API dei provider non possono
  essere garantiti dai test locali.
- Pagine senza dati reali e futuri contenuti molto lunghi possono richiedere un
  nuovo controllo visuale, anche se il layout condiviso è responsive.

## Evidenze di verifica

Al 31 luglio 2026:

- build frontend superata;
- compilazione delle viste superata;
- controllo formale del diff superato;
- 712 test superati, 1 escluso, 2.032 asserzioni;
- migrazioni applicate correttamente su MySQL;
- controllo responsive già eseguito sulle pagine raggiungibili a 375, 768 e
  1440 pixel.

Queste evidenze descrivono lo stato verificato in quella data e vanno aggiornate
dopo modifiche sostanziali.
