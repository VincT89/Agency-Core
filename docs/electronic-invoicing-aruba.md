# Fatturazione elettronica e Aruba

## Stato reale

L'integrazione è pronta lato codice e attende il collaudo con un account Aruba
abilitato alle API. Il gestionale genera l'XML FatturaPA, esegue la verifica
Aruba senza invio, trasmette una sola volta quando autorizzato, riceve le
callback e riconcilia gli stati come controllo aggiuntivo.

Questo non significa che la produzione sia già certificata. Prima dell'uso
reale servono credenziali valide, servizio Premium o delega compatibile,
configurazione delle callback, collaudo DEMO e conferma del commercialista sul
caso fiscale effettivo.

## Perimetro supportato

Il primo perimetro volutamente limitato comprende:

- fattura ordinaria `TD01`;
- formato privati `FPR12`;
- valuta `EUR`;
- emittente italiano;
- clienti privati italiani o esteri;
- una condizione di pagamento `TP02` e una modalità FatturaPA `MP01`-`MP23`;
- IBAN obbligatorio per le modalità che lo richiedono;
- aliquote IVA ordinarie oppure aliquota zero con natura e riferimento
  normativo;
- un solo documento XML per fattura.

Non sono ancora supportati:

- fatture verso la Pubblica Amministrazione `FPA12`;
- note di credito e documenti diversi da `TD01`;
- ritenute, casse previdenziali, split payment e bollo virtuale;
- sconti o maggiorazioni fiscali complessi;
- pagamenti rateali multipli;
- allegati FatturaPA;
- fatture ricevute e contabilità passiva Aruba.

Questi casi non vengono improvvisati: i controlli impediscono la preparazione o
l'invio e mostrano cosa deve essere completato.

## Flusso operativo

1. Admin o amministrazione completa profilo fiscale, cliente, righe e
   pagamento.
2. Il gestionale ricalcola imponibile, IVA e totale sul server.
3. Con `Prepara fattura elettronica` assegna il numero fiscale, salva uno
   snapshot e blocca le modifiche. Non contatta Aruba.
4. `Verifica con Aruba` genera l'XML e usa `dryRun=true`. La fattura non viene
   inviata allo SdI.
5. L'invio effettivo compare soltanto se quella precisa versione dell'XML ha
   superato la verifica e il blocco di sicurezza è abilitato.
6. Il gestionale trasmette senza retry automatico dell'upload e conserva il
   riferimento restituito da Aruba.
7. Callback e sincronizzazione periodica registrano gli aggiornamenti Aruba e
   le notifiche SdI.
8. Admin e amministrazione ricevono una notifica interna per consegna, scarto o
   altro esito finale rilevante.

Il download dell'XML è disponibile dalla pagina della fattura dopo la
preparazione.

## Protezione dal doppio invio

- La preparazione e la numerazione sono transazionali.
- Un doppio clic sulla verifica riusa il risultato già valido per lo stesso
  hash XML.
- L'invio reale richiede una verifica `dryRun` corrispondente, salvo esplicita
  disattivazione di questa regola in configurazione.
- Quando parte l'upload reale, la fattura entra subito in `transmitting` e non
  accetta un secondo invio concorrente.
- Nessun retry HTTP viene applicato automaticamente all'upload.
- Se la risposta è certa e negativa, la fattura torna `ready` e può essere
  corretta o ritentata.
- Se la risposta è assente o ambigua, resta bloccata in `transmitting` finché
  lo stato non viene riconciliato. Non va premuto nuovamente l'invio.
- Il codice Aruba `0034`, relativo a un file già ricevuto, viene trattato come
  esito da verificare e non come autorizzazione a reinviare.

## Stati registrati

| Stato gestionale | Significato |
| --- | --- |
| `not_prepared` | Bozza fiscale modificabile. |
| `ready` | Snapshot bloccato, non inviato. |
| `transmitting` | Invio avviato o esito ancora incerto. |
| `sent` | Presa in carico da Aruba o inviata allo SdI. |
| `delivered` | Consegnata. |
| `delivery_failed` | Non consegnata, notifica `MC`. |
| `undeliverable` | Recapito impossibile, notifica `AT`. |
| `rejected` | Scartata, notifica `NS`. |
| `accepted` | Accettata, notifica `NE EC01`. |
| `refused` | Rifiutata, notifica `NE EC02`. |
| `terms_expired` | Decorrenza termini, notifica `DT`. |
| `processing_error` | Errore di elaborazione Aruba. |

Uno scarto o un errore di elaborazione permette la riapertura controllata. Il
numero fiscale già riservato viene mantenuto e la cronologia precedente non
viene cancellata.

## Configurazione

Le variabili sono presenti in `.env.example`:

```dotenv
ARUBA_EINVOICING_ENABLED=false
ARUBA_EINVOICING_ENV=demo
ARUBA_EINVOICING_USERNAME=
ARUBA_EINVOICING_PASSWORD=
ARUBA_EINVOICING_CALLBACK_KEY=
ARUBA_EINVOICING_ALLOW_SEND=false
ARUBA_EINVOICING_REQUIRE_DRY_RUN=true
ARUBA_EINVOICING_SIGNATURE_DOMAIN=
ARUBA_EINVOICING_SIGNATURE_CREDENTIAL=
ARUBA_EINVOICING_CONNECT_TIMEOUT=5
ARUBA_EINVOICING_HTTP_TIMEOUT=20
```

Regole:

- `ARUBA_EINVOICING_ENV` accetta soltanto `demo` o `production`;
- gli URL ufficiali di autenticazione e web service sono derivati
  dall'ambiente e non vanno inseriti manualmente;
- `ARUBA_EINVOICING_CALLBACK_KEY` deve contenere almeno 32 caratteri;
- `ARUBA_EINVOICING_ALLOW_SEND=false` consente il test connessione e il
  `dryRun`, ma blocca l'upload effettivo;
- `ARUBA_EINVOICING_REQUIRE_DRY_RUN=true` va mantenuto;
- dominio e credenziale di firma sono opzionali e vanno compilati soltanto se
  previsti dal contratto Aruba;
- in produzione `APP_URL` deve essere HTTPS;
- dopo ogni modifica al file `.env` vanno ricostruite le cache di
  configurazione e riavviati i processi persistenti.

Username, password e chiave callback non vengono salvati nel profilo fiscale e
non sono mostrati nell'interfaccia.

## Callback da configurare su Aruba

Il pannello `Dati fiscali e Aruba` mostra gli URL completi dell'ambiente
corrente. I percorsi sono:

```text
POST /api/v1/integrations/aruba/updateInvoiceStatus
POST /api/v1/integrations/aruba/createNotification
```

La chiave statica configurata su Aruba deve essere inviata nell'header
`Authorization` e deve coincidere con `ARUBA_EINVOICING_CALLBACK_KEY`.

Le callback:

- accettano soltanto JSON valido;
- rifiutano richieste prive della chiave corretta;
- deduplicano le consegne ripetute;
- archiviano separatamente e in forma cifrata l'XML della notifica;
- non inseriscono Base64, token o credenziali nei log;
- non fanno retrocedere lo stato quando arriva un evento più vecchio;
- rispondono senza effetti alle fatture in ingresso, fuori dal perimetro
  attuale.

Aruba dichiara fino a 10 tentativi di consegna a intervalli di 3 ore quando la
callback non ottiene una risposta valida.

## Sincronizzazione di sicurezza

Lo scheduler esegue ogni cinque minuti:

```bash
php artisan invoices:sync-electronic-statuses --limit=5
```

Ogni fattura richiede al massimo una lettura del dettaglio e una delle
notifiche. Il limite a cinque fatture mantiene il ciclo entro il limite
documentato di ricerca Aruba. La sincronizzazione non sostituisce le callback,
ma recupera aggiornamenti mancanti o esiti ambigui.

## Dati e log

Per ogni tentativo sono conservati:

- ambiente e modalità, `dry_run` o `live`;
- utente che ha avviato l'operazione;
- hash, nome e contenuto XML cifrato;
- nome assegnato da Aruba e identificativo della richiesta;
- identificativo Aruba e identificativo SdI;
- stato, descrizione leggibile ed eventuale codice errore;
- cronologia deduplicata degli eventi;
- XML delle notifiche SdI cifrato.

I log di integrazione contengono soltanto metadati sanitizzati. Le credenziali,
il token, il file Base64 e la chiave callback non devono comparire nei log né
nel repository.

## Procedura DEMO

1. Attivare il servizio Premium o la delega API e ottenere le credenziali DEMO.
2. Impostare ambiente `demo`, servizio attivo, credenziali e chiave callback.
3. Lasciare `ARUBA_EINVOICING_ALLOW_SEND=false`.
4. Ricostruire la cache di configurazione e usare `Verifica collegamento`.
5. Preparare una fattura con dati concordati e scaricare l'XML.
6. Eseguire `Verifica con Aruba` e confrontare XML, importi e anagrafiche.
7. Impostare `ARUBA_EINVOICING_ALLOW_SEND=true` soltanto per il collaudo
   completo.
8. Inviare nel collaudo Aruba e simulare almeno `RC`, `NS`, `MC` e `AT`.
9. Verificare callback, polling, campanella notifiche e cronologia.
10. Riportare il blocco invio a `false` alla fine del test.

## Passaggio in produzione

1. Ottenere conferma scritta del commercialista su serie, progressivo iniziale,
   regime, modalità di pagamento e casi fiscali realmente usati.
2. Eseguire backup di database e storage.
3. Impostare `APP_URL` pubblico HTTPS e credenziali di produzione.
4. Configurare e provare entrambe le callback nel pannello Aruba Premium.
5. Mantenere `ARUBA_EINVOICING_ALLOW_SEND=false` durante il test connessione e
   il primo `dryRun` di produzione.
6. Confrontare la fattura con il pannello Aruba.
7. Abilitare l'invio e autorizzare una sola prima fattura reale.
8. Attendere lo stato SdI e confrontare ricevuta, XML e pannello Aruba.
9. Abilitare l'uso ordinario soltanto dopo il completamento del primo ciclo.

## Criterio di completamento

Il codice è considerato pronto. L'integrazione esterna sarà considerata pronta
per la produzione soltanto dopo:

- ciclo DEMO completo;
- callback verificate da Aruba;
- primo invio reale autorizzato;
- riconciliazione dello stato finale senza interventi sul database;
- verifica contabile del documento.

Dire che “mancano soltanto le credenziali” è quindi corretto per avviare il
collaudo, ma non è sufficiente per dichiarare conclusa la messa in produzione.

## Riferimenti ufficiali

- [Aruba Fatturazione Elettronica: documentazione API v2](https://fatturazioneelettronica.aruba.it/apidoc/v2/docs.html)
- [Aruba: manuale account Premium](https://guide.pec.it/fatturazione-elettronica/manuale-account-premium.pdf)
- [Specifiche tecniche FatturaPA](https://www.fatturapa.gov.it/export/documenti/Specifiche_tecniche_del_formato_FatturaPA_V1.3.1.pdf)
- [Agenzia delle Entrate: fatturazione elettronica e SdI](https://www1.agenziaentrate.gov.it/web_app_entrate/fatturazione_elettronica.html)
