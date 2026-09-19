# Importazione fatture e materiali del cliente

## Importazione fatture

Da **Fatture > Importa fattura**, Admin e Amministrazione possono acquisire fatture già emesse ai clienti oppure ricevute dai fornitori. Si sceglie un file locale o la ricerca su Aruba, si verifica l’anteprima e si conferma il salvataggio.

- I file XML FatturaPA e XML.P7M vengono letti automaticamente. Per P7M occorre l’estensione PHP OpenSSL con supporto CMS. L’estrazione legge il contenuto, senza certificare l’attendibilità della firma.
- Per i PDF si compilano numero, data, importi e controparte. Il PDF viene conservato come documento originale, senza OCR o servizi esterni.
- Il limite è 10 MB per file. In un XML con più fatture si seleziona il singolo documento da importare.
- Le fatture emesse vengono associate a un cliente già presente oppure a una nuova anagrafica ricavata dall’XML, dopo conferma. Gli identificativi fiscali devono corrispondere. Le anagrafiche esistenti non vengono sovrascritte.
- Le fatture ricevute sono conservate tra i documenti delle spese. Dalla scheda si collegano a una spesa prevista oppure si crea una nuova uscita.
- L’importazione non invia documenti allo SdI, non assegna un nuovo numero fiscale e non registra automaticamente incassi o pagamenti. Gli originali restano scaricabili dagli utenti autorizzati.
- I riferimenti del documento permettono di riconoscere le importazioni ripetute; dati economici discordanti richiedono una verifica. Una fattura già registrata non viene sovrascritta.
- L’anteprima scade dopo 20 minuti. Per le fatture emesse da Aruba l’esito e il contenuto vengono ricontrollati alla conferma.

La lettura automatica gestisce euro e i tipi TD01, TD02, TD03, TD06, TD24 e TD25. Note di credito, autofatture, ritenute e split payment vengono segnalati e non trasformati in fatture ordinarie con importi potenzialmente errati. Il PDF manuale non va usato per aggirare queste differenze contabili. Le rate originali restano nel documento: la registrazione utilizza una sola scadenza, scelta nell’anteprima quando vi sono più rate.

### Aruba

L’importazione usa la configurazione `services.aruba_einvoicing` esistente, le relative credenziali e la partita IVA del profilo fiscale dell’agenzia. Le credenziali non vengono richieste nel modulo né inviate al browser. Il permesso di invio allo SdI non serve per importare.

La ricerca usa gli endpoint v2 `invoices-out` e `invoices-in`, per intervalli di acquisizione di massimo due giorni consecutivi, con paginazione. I file sono acquisiti dai rispettivi endpoint `detail`. Sono necessari credenziali e accesso alle API abilitati per l’utenza Aruba; la verifica con l’utenza effettiva resta distinta dai test con risposte simulate.

Riferimento ufficiale: [API Aruba v2](https://fatturazioneelettronica.aruba.it/apidoc/v2/docs.html).

## Materiali del cliente

La scheda cliente e il progetto marketing collegano all’archivio **Materiali del cliente**. I file possono essere cercati per nome o descrizione e filtrati per loghi, vettoriali, linee guida, modelli/sorgenti e altri materiali.

Sono ammessi JPG, PNG, GIF, WebP, PDF, SVG, AI, EPS, PSD, ZIP, Word, PowerPoint e TXT, massimo 10 MB. Il file originale è conservato nel disco privato degli allegati. I materiali sono scaricati come file, senza eseguire o mostrare nel browser i sorgenti vettoriali.

Admin, Marketing e Fotografo gestiscono i materiali; il Commerciale gestisce quelli dei propri clienti; il Responsabile Operativo mantiene l’accesso ai clienti consentiti. Amministrazione e gli utenti assegnati ai progetti possono consultarli secondo la visibilità del cliente. I documenti generici preesistenti restano nella sezione Allegati e mantengono i precedenti permessi.

## Offerte

Le offerte rifiutate sono mostrate in fondo alle liste e agli storici di cliente e ticket, con titolo e importo sbarrati. Admin e Amministrazione possono caricare allegati ed eliminare un’offerta. L’eliminazione la rimuove dalle liste senza cancellare il progetto eventualmente creato; i dati e i file sono conservati internamente. Il Commerciale scarica gli allegati delle offerte che può vedere, senza modificarli.

AI, stampa PDF delle offerte, template configurabili e voci riutilizzabili non fanno parte di questa consegna.

## Distribuzione

Applicare le migrazioni incluse nel ramo e distribuire gli asset compilati:

```sh
php artisan migrate --force
npm run build
php artisan optimize:clear
```

Se gli asset vengono compilati prima del rilascio, distribuire la cartella generata secondo la procedura già usata su Plesk. Restano necessari il normale scheduler per le ricorrenze e i backup del disco privato degli allegati. Le migrazioni aggiungono colonne e tabelle senza eliminare dati storici. La migrazione del 19 settembre per il paese dell’emittente aggiorna anche le installazioni che avevano già eseguito la prima versione delle spese ricorrenti; non attribuisce un paese presunto ai documenti esistenti.

Il collaudo automatico usa database isolati. Per il caricamento degli esempi richiesto dall’utente, anche il database locale del gestionale viene aggiornato alle migrazioni correnti; la produzione non viene modificata. Vedere [dati dimostrativi locali](local-demo-data.md).
