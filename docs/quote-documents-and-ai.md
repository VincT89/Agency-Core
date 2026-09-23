# Preventivi configurabili, voci salvate e revisione AI

Implementazione del 21 settembre 2026, ramo `main_test`.

## Uso

In **Offerte commerciali**, Admin e Amministrazione possono creare o modificare una bozza e scegliere il cliente dall'anagrafica esistente, oppure aggiungerlo con la procedura comune.

Il modulo mostra subito cliente, oggetto e righe con servizio, quantità (inizialmente 1), prezzo e importo. La ricerca delle voci salvate si apre entrando nel campo: un clic inserisce tutti i testi, le tempistiche e il prezzo. È possibile duplicare una riga, personalizzarla e salvarla in libreria senza uscire dal preventivo. Con un solo risultato, Invio lo inserisce; Tab o Freccia giù permettono di raggiungere i risultati dalla tastiera.

**Descrizione e tempi** apre i dettagli del singolo servizio. **Testi, pagamento e note** contiene i testi generali e la revisione AI; **Data, intestazione e indicazione IVA** contiene le impostazioni già compilate. Chiudere queste sezioni conserva tutti i valori e li include nel salvataggio. Se un campo chiuso non è valido, la sezione viene aperta. Totale e pulsanti di salvataggio restano visibili durante lo scorrimento; sugli schermi molto bassi tornano nel flusso della pagina per lasciare spazio alla compilazione.

Da un'offerta esistente, **Usa come modello** apre un nuovo modulo già compilato con oggetto, servizi, testi, condizioni, nota sul prezzo e indicazioni AI. Il cliente è preselezionato e può essere cambiato. Data e intestazione Sodano partono dai valori correnti; il riferimento è nuovamente automatico. La nuova offerta viene creata soltanto premendo Salva e nasce come bozza indipendente: non copia stato, allegati, anagrafica congelata, collegamenti a ticket/progetti o catena di revisioni. Per modificare la stessa offerta presentata si usa invece **Prepara revisione**.

Sono configurabili oggetto, numero/riferimento (automatico se vuoto), data, descrizione del progetto, servizi, riepilogo breve, descrizione estesa, tempi nel riepilogo, condizioni dettagliate di consegna, quantità, prezzi, indicazione fiscale accanto al totale, pagamento e note. Il totale è sempre calcolato dal server. L'indicazione fiscale è un testo, non applica automaticamente un'aliquota IVA.

**Salva voce in libreria** conserva una copia del servizio, comprese quantità e prezzo. **Voci salvate** permette di cercarla e inserirla in un'altra offerta. Dopo l'inserimento la copia è indipendente: personalizzazioni, revisioni o rimozioni dalla libreria non cambiano le offerte precedenti. I salvataggi identici non creano duplicati. La libreria è condivisa tra Admin e Amministrazione. Per creare una variante, si richiama la voce, si modifica e si salva nuovamente in libreria; la pagina di gestione consente di eliminare le voci non più necessarie.

Il Commerciale mantiene la visibilità delle offerte presentate, accettate e rifiutate dei propri clienti, inclusa la stampa. Non acquisisce permessi di modifica, accesso alla libreria o uso dell'AI.

## Modello e PDF

Il documento riprende il modello fornito: logo e dati Sodano affiancati a sinistra, anagrafica cliente a destra, titolo e riferimento, tabella con intestazione rossa, totale complessivo, descrizione del progetto, dettagli dei servizi, pagamento e note. Le pagine aumentano in base al contenuto. I singoli importi rimangono nel gestionale: nel documento appare il totale, come nel riferimento.

**Salva e anteprima** salva prima la bozza. **Anteprima e stampa PDF** riapre il documento salvato. **Stampa / Salva PDF** apre la stampa del browser: scegliere Salva come PDF, carta A4 e disattivare le intestazioni/piè di pagina del browser. Non è installata alcuna libreria PDF aggiuntiva, secondo la scelta dell'utente. Stampa e numerazione delle pagine sono state collaudate con Chrome; la numerazione dipende dal supporto del browser ai margini di stampa CSS.

L'anagrafica del cliente viene congelata quando l'offerta è registrata come presentata. I dati dell'agenzia sono copiati nella bozza dai Dati fiscali, se presenti; altrimenti dai dati del modello fornito, in `config/quotes.php`. Possono essere modificati per la sola offerta. Le nuove revisioni conservano i campi configurati. I vecchi documenti privi di copia dell'agenzia usano i dati correnti: non è possibile ricostruire automaticamente un'intestazione storica non salvata.

Il PDF allegato dal cliente è un riferimento di impaginazione e non viene importato come offerta nel database reale. Il documento di anteprima generato per il collaudo usa un database separato.

## AI

**Migliora testi con AI** propone una revisione della descrizione del progetto, dei riepiloghi e delle descrizioni dei servizi. L'utente può dare indicazioni stilistiche per la singola offerta, confrontare originale e proposta, applicare o scartare. Applicare una proposta non salva automaticamente la bozza. Il server non aggiorna offerte o libreria durante la richiesta AI.

Le richieste inviano a OpenAI soltanto quei testi, i nomi dei servizi e le indicazioni stilistiche; non includono i campi anagrafici, allegati, importi, quantità, tempi o condizioni. Eventuali dati scritti liberamente nei testi sono naturalmente parte del testo inviato. Le risposte sono validate, i numeri contenuti nei testi devono rimanere invariati, l'output è mostrato come testo semplice. Una proposta superata da modifiche manuali non può sovrascrivere il lavoro successivo.

La revisione riguarda solo i campi già compilati. Se l'AI aggiunge testo in un campo lasciato vuoto, quel campo resta vuoto e le revisioni valide degli altri testi rimangono disponibili nell'anteprima. Una modifica ai numeri o la cancellazione di un testo esistente continua a bloccare la proposta, indicando il campo interessato. Non vengono generati automaticamente servizi o descrizioni mancanti a partire dal solo nome.

Per attivare il pulsante, configurare sul server:

```dotenv
OPENAI_API_KEY=
OPENAI_QUOTE_MODEL=gpt-4.1-mini
```

La chiave rimane sul server e non è mai resa alla pagina. Si usa Responses API con `store: false`, senza strumenti esterni e senza salvataggio dei prompt nei log applicativi. Massimo cinque richieste al minuto per utente, limite di dimensione dei testi e timeout. Le chiamate reali richiedono un account API abilitato; il collaudo locale usa risposte simulate e non consuma credito. La generazione manuale e la stampa restano utilizzabili senza AI.

Fonti: [OpenAI Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs), [modello GPT-4.1 mini](https://developers.openai.com/api/docs/models/gpt-4.1-mini), [margini di stampa e numerazione in Chrome](https://developer.chrome.com/blog/print-margins).

## Distribuzione

La nuova migrazione aggiunge campi alle offerte e alle righe e crea la tabella `quote_services`. Non modifica i valori delle offerte esistenti.

```sh
php artisan migrate --force
npm run build
php artisan optimize:clear
```

Se le variabili OpenAI cambiano su un server con configurazione memorizzata in cache, aggiornare anche tale cache con la procedura abituale del progetto. Non inserire la chiave nel repository.

## Verifiche eseguite

Correzione del 23 settembre 2026: una chiamata OpenAI reale ha riprodotto il rifiuto della proposta quando il solo testo compilato era «rifacimento della homepage con animazioni» e l'AI riempiva i dettagli vuoti del servizio. Dopo la correzione, una nuova chiamata reale è stata accettata mantenendo vuoti quei campi. Superati 36 test con 441 asserzioni e il controllo di formattazione PHP. Nel browser isolato è stata riutilizzata anche la risposta reale prima rifiutata: anteprima, applicazione, prezzo di prova e quantità invariati, salvataggio e riapertura verificati; nessun errore JavaScript o scorrimento orizzontale a 1440, 768, 390 e 320 pixel. La chiave locale è stata riconosciuta senza esporla; nessuna modifica al database reale e nessuna distribuzione in produzione.

Semplificazione del modulo: 32 test, 421 asserzioni superate sulle offerte, documenti e flusso commerciale; build e formattazione PHP superate. Collaudo nel browser su database isolato: 6 flussi completi e 9 layout da 320 a 1440 pixel, senza errori JavaScript né controlli fuori schermo. Verificati inserimento dalla libreria con mouse e tastiera, duplicazione/riordino/rimozione righe, salvataggio con sezioni chiuse, apertura dei campi non validi, riuso per un altro cliente con originale invariato, permessi e anteprima AI simulata. Nessuna nuova migrazione o modifica al database applicativo per questa semplificazione.

Suite offerte/documenti/flusso commerciale: 30 test, 384 asserzioni. Dopo il rafforzamento della validazione delle risposte AI: 9 test documenti/AI, 117 asserzioni. Build Vite e formattazione dei file PHP superate. Browser: 6 flussi e 12 layout fra 320 e 1440 pixel, senza errori JavaScript o controlli fuori schermo. PDF di riferimento su 2 pagine; prova estrema su 37 pagine, con 600 paragrafi e 300 riepiloghi conservati, numerazione corretta e nessun testo fuori pagina.

Alla consegna del 21 settembre, la migrazione era stata applicata al solo database locale `agency_core` su `127.0.0.1`; i valori preesistenti di offerte e righe erano stati confrontati prima e dopo e risultavano invariati. La chiave OpenAI non era ancora configurata in locale. Per quella consegna non erano state eseguite chiamate AI reali, push o distribuzioni in produzione.
