# Fatturazione elettronica e Aruba

## Stato reale

La fondazione fiscale interna è pronta. L'integrazione Aruba non è ancora
implementata.

Preparare una fattura nel gestionale oggi significa validare i dati, assegnare
un numero, congelare uno snapshot e impedire modifiche accidentali. Non viene
generato alcun XML e non parte alcuna chiamata verso Aruba o lo SdI.

## Funzioni già implementate

### Profilo fiscale dell'agenzia

Sono gestiti:

- denominazione;
- partita IVA e codice fiscale;
- regime fiscale;
- indirizzo, CAP, comune, provincia e Stato;
- email e PEC;
- codice destinatario;
- IBAN;
- serie e numero iniziale della numerazione fiscale.

Il profilo è accessibile soltanto ad admin e amministrazione.

### Dati del cliente

Sono disponibili partita IVA, codice fiscale, indirizzo, Stato, PEC, email di
fatturazione e codice destinatario. Per i clienti italiani la preparazione
richiede partita IVA o codice fiscale e almeno codice destinatario o PEC.

### Voci e importi

Ogni riga contiene descrizione, quantità, unità di misura, prezzo, aliquota IVA,
natura e riferimento normativo per le operazioni a IVA zero. Imponibile, IVA e
totale sono ricalcolati sul server.

### Preparazione fiscale

Il sistema:

1. supporta attualmente soltanto `TD01`;
2. verifica i dati dell'agenzia, del cliente e delle righe;
3. assegna un numero progressivo separato per anno e serie;
4. crea uno snapshot fiscale immutabile;
5. blocca modifica, eliminazione e variazione delle righe;
6. consente di riaprire una fattura non inviata mantenendo il numero già
   riservato;
7. rende idempotente il doppio clic sulla preparazione.

## Stati fiscali presenti

| Stato | Significato previsto |
| --- | --- |
| `not_prepared` | Bozza ancora modificabile. |
| `ready` | Validata e bloccata, ma non inviata. |
| `transmitting` | Invio al provider in corso. |
| `sent` | Presa in carico dal provider o inoltrata, secondo la futura mappatura. |
| `delivered` | Consegna confermata. |
| `delivery_failed` | Mancata consegna. |
| `rejected` | Fattura scartata. |

Solo i primi due stati sono attualmente raggiunti dal flusso applicativo. La
mappatura precisa degli altri stati deve essere definita usando le risposte e le
notifiche Aruba.

## Parti mancanti

Non sono ancora presenti:

- generatore XML FatturaPA;
- validazione contro schema e controlli applicabili;
- nomi file conformi e gestione progressivo invio;
- client HTTP Aruba;
- autenticazione, cache e refresh dei token;
- `dryRun` Aruba;
- invio effettivo;
- persistenza di nome file Aruba, identificativo richiesta e identificativo
  SdI;
- endpoint callback autenticato;
- polling di riconciliazione come fallback;
- gestione delle notifiche di scarto, consegna e mancata consegna;
- retry e idempotenza specifici per l'invio fiscale;
- download e conservazione dei file e delle ricevute;
- costruzione completa dei dati di pagamento FatturaPA, inclusi modalità,
  condizioni, scadenze e coordinate previste dal tracciato;
- note di credito, ritenute, casse previdenziali, bollo, sconti complessi,
  fatture PA e altri tipi documento.

Di conseguenza, aggiungere username e password Aruba nel `.env` oggi non produce
alcun effetto.

## Requisiti Aruba

La documentazione Aruba API v2 prevede:

- ambiente DEMO e ambiente PRODUZIONE con base URL distinti;
- accesso ai web service per utenti Premium o utenze gestite tramite delega
  idonea;
- autenticazione `POST /auth/signin` con credenziali nel body
  `application/x-www-form-urlencoded`;
- access token con durata dichiarata di 30 minuti e possibilità di refresh;
- invio di XML non firmato tramite `POST /services/invoice/upload`;
- file XML codificato Base64 nel campo `dataFile`;
- opzione `dryRun=true` per validare senza inviare allo SdI;
- ricerca delle fatture e delle notifiche;
- callback push per fatture, notifiche e cambi di stato;
- callback autenticata con API key statica oppure con il metodo configurato nel
  pannello Premium.

La documentazione indica inoltre limiti di frequenza e dimensione, inclusi 5 MB
per il file e 30 richieste di upload al minuto per IP. Il client dovrà quindi
gestire cache del token, `429`, backoff e riconciliazione.

Aruba ritenta le callback non consegnate secondo la propria policy; la
documentazione corrente indica fino a 10 tentativi a intervalli di 3 ore. Il
nostro endpoint dovrà essere idempotente, perché la stessa notifica può arrivare
più volte.

## Attività richieste all'utente Aruba

1. Attivare il servizio adatto all'uso API o una delega compatibile.
2. Completare il primo accesso e le verifiche richieste, incluso OTP quando
   previsto.
3. Richiedere o abilitare l'ambiente DEMO.
4. Confermare partita IVA mittente e soggetto titolare del servizio.
5. Configurare dal pannello la callback HTTPS quando l'endpoint sarà disponibile.
6. Generare o configurare la credenziale di autenticazione della callback.
7. Conservare username, password e chiave callback fuori dal repository.

Questi passaggi non possono essere sostituiti dal solo codice del gestionale.

## Architettura da implementare

### Configurazione

La configurazione finale dovrà distinguere almeno:

- abilitazione globale;
- ambiente `demo` o `production`;
- username e password;
- URL di autenticazione e API derivati dall'ambiente;
- segreto della callback;
- timeout e limiti di retry.

I nomi delle future variabili `ARUBA_*` verranno definiti insieme al codice. Non
vanno documentati come operativi prima che esistano in `config` e
`.env.example`.

### Componenti

1. `FatturaPaXmlBuilder`: trasforma lo snapshot fiscale in XML.
2. `FatturaPaValidator`: verifica schema e regole supportate.
3. `ArubaAuthenticator`: ottiene, memorizza e rinnova il token.
4. `ArubaInvoiceClient`: esegue dry-run, upload e ricerche.
5. Action transazionale di invio: blocca la fattura e crea una richiesta
   idempotente.
6. Job di trasmissione: separa il click dell'utente dalla chiamata esterna.
7. Callback controller: autentica, valida, deduplica e aggiorna lo stato.
8. Job di riconciliazione: recupera gli esiti mancanti o ambigui.
9. Audit e telemetria: registra identificativi ed esiti senza salvare segreti.

### Persistenza necessaria

Oltre allo stato attuale serviranno almeno:

- hash dell'XML inviato;
- nome file locale e nome assegnato da Aruba;
- identificativo della richiesta;
- identificativo SdI;
- data di invio e ultimo aggiornamento;
- ultimo codice e descrizione di errore sanitizzata;
- payload delle notifiche strettamente necessario;
- chiave di idempotenza;
- riferimenti a ricevute e file conservati.

## Flusso target

1. L'operatore completa profilo, cliente, righe e pagamento.
2. Il gestionale prepara e blocca la fattura.
3. Genera XML da quello stesso snapshot.
4. Valida localmente formato e regole supportate.
5. Esegue `dryRun` Aruba in DEMO.
6. Se il risultato è valido, l'operatore autorizza l'invio reale.
7. Il job autentica, invia una sola volta e salva gli identificativi.
8. Callback o polling aggiornano stato e ricevute.
9. Uno scarto permette un nuovo documento corretto secondo la procedura
   contabile concordata; non si modifica silenziosamente ciò che è già partito.

## Gestione degli errori

- `401`: rinnovare il token una sola volta; se persiste, richiedere intervento
  sulle credenziali.
- `429`: rispettare attesa e backoff, senza creare un nuovo invio logico.
- timeout prima della risposta: riconciliare per nome file, hash o
  identificativo prima di ritentare.
- errore Aruba `0034` o indicazione di file già inviato: trattare come possibile
  duplicato e cercare l'invio esistente.
- scarto SdI: memorizzare codice e descrizione, mostrare un messaggio chiaro e
  richiedere intervento amministrativo.
- callback duplicata: rispondere in modo idempotente senza ripetere gli effetti.

## Piano di collaudo

### Test automatici

- XML valido per il caso TD01 supportato;
- caratteri speciali e arrotondamenti;
- aliquota zero con natura e riferimento;
- numerazione concorrente;
- token scaduto e refresh;
- dry-run riuscito e fallito;
- timeout e `429`;
- risposta duplicato;
- callback valida, non autorizzata, duplicata e fuori ordine;
- riconciliazione dopo esito ambiguo;
- impossibilità di modificare una fattura già trasmessa.

### DEMO Aruba

1. Test connessione e verifica utenza.
2. Invio dry-run di una fattura controllata.
3. Invio DEMO completo.
4. Ricezione degli stati previsti.
5. Simulazione di scarto e mancata consegna.
6. Verifica del retry callback.
7. Confronto tra XML, snapshot, importi e pannello Aruba.

### Produzione

1. Credenziali inserite nel secret store.
2. Callback HTTPS verificata.
3. Kill switch inizialmente disattivato.
4. Una sola fattura reale autorizzata dall'amministrazione.
5. Confronto con pannello Aruba e ricevuta SdI.
6. Attivazione ordinaria soltanto dopo riconciliazione completa.

## Criterio di completamento

La fatturazione elettronica sarà considerata pronta soltanto quando una fattura
DEMO avrà completato generazione XML, validazione, upload, callback o polling e
stato finale senza interventi sul database. Per la produzione servirà poi una
prima fattura reale verificata anche nel pannello Aruba.

La correttezza tecnica non sostituisce la verifica del commercialista su
numerazione, regime fiscale, bollo, ritenute e casi documentali effettivamente
necessari all'agenzia.

## Riferimenti ufficiali

- [Aruba Fatturazione Elettronica: documentazione API v2](https://fatturazioneelettronica.aruba.it/apidoc/v2/docs.html)
- [Aruba: manuale account Premium](https://guide.pec.it/fatturazione-elettronica/manuale-account-premium.pdf)
- [Specifiche tecniche operative FatturaPA](https://www.fatturapa.gov.it/export/documenti/Specifiche_tecniche_del_formato_FatturaPA_V1.3.1.pdf)
- [Agenzia delle Entrate: fatturazione elettronica e SdI](https://www1.agenziaentrate.gov.it/web_app_entrate/fatturazione_elettronica.html)
