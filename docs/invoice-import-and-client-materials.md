# Importazione fatture e materiali del cliente

## Importazione fatture

Da **Fatture > Importa fattura**, Admin e Amministrazione possono acquisire fatture già emesse ai clienti oppure ricevute dai fornitori. Si sceglie un file locale o la ricerca su Aruba, si verifica l’anteprima e si conferma il salvataggio.

Il modulo **Dati fiscali** permette di salvare il profilo senza IBAN, anche con Bonifico come pagamento predefinito, e procedere all’importazione XML o ZIP. Il controllo sull’identità dell’agenzia resta obbligatorio. Un IBAN compilato deve rispettare il formato previsto; per preparare nuove fatture con modalità che lo richiedono, il controllo di completezza fiscale continua a bloccare la preparazione finché manca l’IBAN, senza assegnare numeri o inviare documenti.

- I file XML FatturaPA e XML.P7M vengono letti automaticamente. Per P7M occorre l’estensione PHP OpenSSL con supporto CMS. L’estrazione legge il contenuto, senza certificare l’attendibilità della firma.
- Per i PDF si compilano numero, data, importi e controparte. Il PDF viene conservato come documento originale, senza OCR o servizi esterni.
- Il limite è 10 MB per file. In un XML con più fatture si seleziona il singolo documento da importare.
- Le fatture emesse vengono associate a un cliente già presente oppure a una nuova anagrafica ricavata dall’XML, dopo conferma. Gli identificativi fiscali devono corrispondere. Le anagrafiche esistenti non vengono sovrascritte.
- Le fatture ricevute sono conservate tra i documenti delle spese. Dalla scheda si collegano a una spesa prevista oppure si crea una nuova uscita.
- L’importazione non invia documenti allo SdI, non assegna un nuovo numero fiscale e non registra automaticamente incassi o pagamenti. Gli originali restano scaricabili dagli utenti autorizzati.
- I riferimenti del documento permettono di riconoscere le importazioni ripetute; dati economici discordanti richiedono una verifica. Una fattura già registrata non viene sovrascritta.
- L’anteprima scade dopo 20 minuti. Per le fatture emesse da Aruba l’esito e il contenuto vengono ricontrollati alla conferma.

La lettura automatica gestisce euro e i tipi TD01, TD02, TD03, TD06, TD24 e TD25. Per i documenti emessi supporta anche TD04 e split payment: le note di credito sono documenti separati e il netto da incassare viene distinto dal totale IVA inclusa. Per i documenti ricevuti, TD04 e split payment richiedono ancora una gestione distinta. Autofatture e ritenute vengono segnalate senza importarle come fatture ordinarie. Il PDF manuale non va usato per aggirare queste differenze contabili. Le rate originali restano nel documento: la registrazione utilizza una sola scadenza, scelta nell’anteprima quando vi sono più rate.

### ZIP Aruba e duplicati

Da **Fatture > Importa ZIP** si legge l’esportazione dei documenti emessi e si confermano i nuovi documenti e gli eventuali clienti mancanti. Occorre l’estensione PHP ZIP. Limiti: 100 documenti XML/XML.P7M, 10 MB per archivio o singolo documento e 20 MB complessivi decompressi. Non vengono estratti percorsi sul disco; archivi cifrati, percorsi anomali e compressioni eccessive sono rifiutati.

FPA e FPR restano nella stessa lista con il numero originale completo, per esempio `FPA 1/26` e `FPR 1/26`, e il tipo documento. Il progetto è facoltativo anche nella creazione e modifica delle fatture gestionali.

La lista offre i filtri **Emessa dal / Emessa al** (estremi inclusi) e **Numero documento**, anche parziale. Si possono usare insieme a tipo documento, ricerca libera, cliente e stato; restano applicati cambiando pagina. Il numero dedicato cerca la numerazione visibile del documento, senza includere i nomi dei clienti o gli identificativi interni di importazione.

Il contatore **Fatture** nella sidebar indica le fatture ancora da incassare, comprese quelle importate e senza progetto. Conta i documenti emessi, parzialmente incassati o scaduti con residuo positivo, al netto di incassi, split payment e note di credito collegate. Esclude bozze, fatture annullate, saldate e interamente stornate; le note non sono conteggiate come fatture da incassare. È visibile solo ai profili abilitati alla finanza e si aggiorna al caricamento della pagina successivo a ogni operazione. Quando non ci sono residui, il contatore scompare come gli altri contatori della sidebar.

Nella pagina **Fatture**, **Da incassare** mostra anche il numero di fatture con residuo aperto. Il contatore, il filtro e l’indicatore **Scadute** usano la scadenza precedente a oggi e il residuo positivo, comprese le importate e quelle parzialmente incassate. La scadenza odierna non è ancora scaduta. La visualizzazione non attende lo scheduler e non riscrive lo stato o i dati originali del documento. Il dettaglio e i contatori della dashboard usano lo stesso criterio.

L’identità di importazione comprende emittente, anno e numero completo. Il contenuto commerciale viene confrontato anche quando cambiano soltanto descrizioni o condizioni; nome del file, prefissi XML, spaziatura tra elementi e progressivo di trasmissione non determinano una nuova fattura. I duplicati vengono saltati; i conflitti sono mostrati nell’esito senza sostituire documenti, allegati, anagrafiche, incassi o collegamenti esistenti. Il controllo è ripetuto nella transazione di salvataggio.

Le nuove fatture importate partono da **Da incassare**, nel progetto condiviso **Interventi e vendite**, senza incassi creati dai dati di pagamento dell’XML. Più corrispondenze fiscali per un cliente richiedono una verifica anziché una scelta automatica. Gli originali sono scaricabili e protetti dall’eliminazione anche per l’amministratore.

Il contenitore **Interventi e vendite** è unico per tutti i clienti e viene creato dalla migrazione dedicata, senza modificare le fatture già presenti. Nella creazione manuale è preselezionato: si può scegliere un progetto specifico del cliente oppure nessun progetto. Soltanto questo contenitore è selezionabile per clienti diversi; gli altri progetti conservano il controllo sul cliente. Le fatture esistenti, anche se prive di progetto, mantengono la loro associazione durante le importazioni ripetute. Le note di credito restano separate e le quote collegate seguono il progetto della fattura nei report.

Nel riepilogo per progetto il contenitore aggrega le fatture associate; nel riepilogo per cliente ogni fattura e incasso resta attribuito al proprio cliente. Il contenitore può essere rinominato e avere un team, ma deve restare attivo, senza un cliente esclusivo, e non può essere eliminato. Nessun dato preesistente viene trasferito automaticamente nel contenitore.

### Visualizzazione e stato delle fatture importate

Nel dettaglio, **Visualizza fattura** apre una copia di cortesia leggibile dell’XML originale, con emittente, cliente, righe, riepiloghi IVA e pagamenti previsti. Il pulsante **Stampa / Salva PDF** usa la stampa del browser in formato A4. Anche le note di credito hanno un’anteprima dedicata. I dati anagrafici provengono dall’originale conservato, senza dipendere dalle modifiche successive all’anagrafica o al profilo fiscale. Il documento non è un PDF originale di Aruba.

La dicitura delle fatture importate è **Importata manualmente**. **Modifica stato** consente agli utenti abilitati di gestire Da incassare, Parzialmente incassata, Incassata, Scaduta e Annullata nel gestionale. Un incasso totale registra solo il saldo ancora dovuto, tenendo conto di split payment e note di credito; un incasso parziale richiede l’importo del nuovo pagamento. Data e metodo sono obbligatori. Per ridurre un incasso già registrato occorre correggere il relativo pagamento.

**Modifica scadenza**, nel dettaglio della fattura, consente agli stessi utenti di aggiornare la scadenza gestionale, anche sui documenti importati. La nuova data non può precedere l’emissione. Il salvataggio aggiorna gli indicatori di scadenza e la previsione degli incassi, senza cambiare pagamenti, importi, XML o dati fiscali originali; è registrato nello storico. Un modulo superato da una modifica concorrente viene respinto. Reimportare il documento conserva la scadenza gestionale scelta.

L’annullamento richiede la conferma standard, non invia richieste ad Aruba o allo SdI e conserva XML e dati importati. È bloccato in presenza di incassi o note di credito collegate. Una fattura annullata ha residuo zero e può tornare Da incassare. Il salvataggio controlla autorizzazione e versione degli importi all’interno della transazione, impedendo doppi incassi da invii ripetuti. Una nuova importazione conserva gli stati e gli incassi esistenti.

### Note di credito

Le TD04 importate si consultano nella stessa lista delle fatture, con numerazione originale e dettaglio separato. Il filtro Tipo documento permette di vedere tutti i documenti, solo fatture TD01 o solo note TD04. Ricerca, ordinamento per data e paginazione includono entrambi i tipi; i filtri d’incasso riguardano soltanto le fatture. Per le note sono mostrati il credito ancora da collegare e gli eventuali collegamenti, senza presentarli come incassi. Una nota può essere collegata a più fatture dello stesso cliente e valuta, indicando la quota da stornare dal dovuto, entro il credito disponibile e la parte ancora stornabile della fattura.

I riferimenti strutturati o scritti nelle descrizioni sono suggerimenti da verificare, senza collegamenti automatici. Le proposte e il salvataggio confrontano gli identificativi fiscali originali di cliente ed emittente: paese (`IdPaese`) e partita IVA (`IdCodice`), con controllo dei codici fiscali quando presenti. Se entrambi i documenti non hanno partita IVA, occorrono paese e codice fiscale corrispondenti. Le modifiche successive all’anagrafica non sostituiscono i dati dell’XML; identità discordanti o incomplete bloccano il collegamento.

Il pulsante **Usa suggerimento** compila fattura e importo senza salvare. Compare solo se numero, anno ed eventuale data individuano una sola fattura compatibile e la quota delle righe XML è ricostruibile senza ambiguità: imponibile, IVA e totale devono tornare, rispettando il netto dello split payment. Prefissi FPA/FPR espliciti vengono conservati; riferimenti incompleti o ambigui richiedono verifica manuale. Si conferma con **Collega fattura**, entro i limiti disponibili. Fatture mancanti possono essere importate e collegate successivamente. Una nuova importazione non modifica i collegamenti già confermati. Rimuovere un collegamento richiede la conferma standard e conserva entrambi i documenti.

Il collegamento riduce il residuo senza modificare il totale originale né creare pagamenti. Per una fattura già pagata viene esposto il credito del cliente da regolare; la registrazione di rimborsi non è inclusa. I riepiloghi sottraggono gli imponibili delle note dal fatturato alla data della nota e calcolano il residuo sul netto dovuto, al netto dei crediti collegati e degli incassi. Per progetto sono attribuite soltanto le quote collegate, riproporzionando l’imponibile al totale pagabile della nota, anche nei casi di split payment. L’emissione o l’invio allo SdI di nuove TD04 non è incluso nel flusso di importazione.

La migrazione `2026_10_16_120000_add_credit_notes_and_invoice_payable_amounts.php` deve essere applicata durante il rilascio, prima di usare le nuove schermate. Aggiunge le tabelle delle note e dei collegamenti e le colonne per netto e crediti sulle fatture; non sostituisce i documenti esistenti.

### Report e previsione

Nei riepiloghi economici, grafici e tabelle usano lo stesso intervallo di date, inclusi i giorni iniziale e finale. Gli incassi seguono la data del pagamento anche quando la fattura è stata emessa in un altro periodo. Il fatturato esposto è IVA esclusa: somma gli imponibili delle fatture e sottrae quelli delle note alla data della loro emissione, anche se non ancora collegate; un mese con sole note di credito può quindi essere negativo. Lo stesso calcolo alimenta il fatturato nella dashboard e nella pagina Fatture; in quest’ultima i riquadri riportano i totali generali, indipendentemente dai filtri della lista. Il residuo è quello attuale delle fatture emesse nel periodo selezionato, non una ricostruzione storica alla data finale. Le spese annullate sono escluse dai costi per cliente e progetto.

Le note ancora da collegare restano consultabili nella lista Fatture e nel proprio dettaglio: non riducono automaticamente il residuo di una fattura. Il riepilogo per progetto comprende solo movimenti associati a un progetto e quote di credito confermate; le fatture senza progetto restano nel riepilogo generale e per cliente. Senza un saldo iniziale configurato, la previsione mostra i movimenti e la variazione, lasciando il saldo previsto come «Non impostato».

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
