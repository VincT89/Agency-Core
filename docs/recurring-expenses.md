# Spese ricorrenti e previsione di cassa

La sezione **Spese** comprende Ricorrenze, Entrate manuali, Documenti e Previsione. È condivisa fra Admin e Amministrazione secondo i permessi finanziari esistenti. Documenti e cedolini non sono accessibili agli altri ruoli.

## Uso

- Una ricorrenza genera uscite **da pagare**, con frequenza settimanale, mensile, trimestrale, semestrale o annuale. L’orizzonte viene esteso ogni giorno fino a 24 mesi, senza duplicare le scadenze esistenti.
- Il giorno resta ancorato alla prima scadenza: 31 gennaio, ultimo giorno di febbraio, 31 marzo. La data finale, quando indicata, è inclusa.
- Gli importi programmati sono previsioni. Per segnare una spesa come pagata occorrono il documento appropriato e la data effettiva del pagamento. Sono supportati fattura, cedolino, ricevuta e altro giustificativo.
- Una fattura può essere collegata a più rate; la somma delle spese non annullate non può superare l’importo documentato. Per un cedolino l’importo inserito è il netto da corrispondere; altre uscite vanno registrate separatamente.
- Le modifiche a una ricorrenza aggiornano solo scadenze future non pagate, senza documento e non modificate singolarmente. La sospensione annulla queste previsioni. La riattivazione ripristina quelle future senza generare nuovi arretrati per il periodo sospeso. Frequenza e prima scadenza restano fisse; per cambiarle si termina la vecchia ricorrenza e se ne crea una nuova.
- Le entrate manuali sono dedicate ai movimenti fuori fattura. I pagamenti delle fatture clienti continuano a essere registrati dalla fattura, evitando di inserirli anche come entrate manuali.

## Calcolo della previsione

Il saldo iniziale rappresenta la disponibilità **a inizio giornata** della data scelta. I movimenti effettivi precedenti sono esclusi. Senza un saldo iniziale vengono mostrati flussi e variazioni, senza inventare un saldo disponibile.

Ogni mese combina incassi già registrati, residui delle fatture da incassare, entrate manuali, spese pagate e spese previste. Le fatture parzialmente incassate contribuiscono alle entrate attese solo per il residuo. Le voci annullate sono escluse. Le scadenze ancora aperte precedenti alla data iniziale e le fatture senza scadenza sono mostrate nel primo mese con un avviso.

Il prospetto gestisce euro e orizzonti di 6, 12 e 24 mesi. Esclude le fatture in altre valute e i relativi pagamenti, mostrando un avviso; non applica cambi impliciti. I grafici preesistenti della fatturazione mantengono il proprio perimetro: il nuovo prospetto include anche le entrate manuali.

## Documenti e Aruba

Il caricamento manuale acquisisce metadati e file privato, massimo 10 MB (PDF, XML, JPG o PNG). I riferimenti fiscali identificano il documento ed evitano duplicati; un importo diverso sugli stessi riferimenti viene segnalato.

L’integrazione usa la configurazione Aruba esistente e la partita IVA del profilo fiscale dell’agenzia. La ricerca delle fatture ricevute avviene per intervalli di massimo due giorni consecutivi, con paginazione. L’importazione verifica il destinatario nell’XML, la valuta, il tipo di documento e il corpo selezionato nel caso di lotti. Note di credito e documenti non in euro non vengono registrati come uscite positive.

L’importazione acquisisce il documento: l’utente lo collega alla spesa prevista oppure crea una nuova uscita. Non conferma automaticamente pagamenti e non crea spese duplicate a ogni ricerca. I file XML e PDF non sono salvati nei log di risposta del provider.

Riferimento: [API Aruba per fatturazione elettronica](https://fatturazioneelettronica.aruba.it/apidoc/v2/docs.html). La disponibilità delle API dipende dall’utenza e dal servizio Aruba configurati. I test automatici simulano le risposte del provider; occorre una verifica del collegamento con l’utenza effettiva dopo la configurazione.

## Distribuzione

Sul progetto aggiornato applicare la migrazione:

```sh
php artisan migrate --force
```

La migrazione aggiunge tabelle e colonne; non riscrive importi o stati delle spese storiche. Distribuire anche gli asset generati da `npm run build` secondo la procedura del progetto.

Il comando `php artisan expenses:generate-recurring` estende le scadenze ed è ripetibile. È già registrato nello scheduler alle 01:00; su Plesk deve essere attivo il normale avvio ogni minuto di `php artisan schedule:run`. Non aggiungere un secondo avvio se lo scheduler del gestionale è già configurato.

Per file fino a 10 MB, verificare che i limiti PHP e del web server per upload e dimensione della richiesta non siano inferiori al limite applicativo. I file restano nel disco privato `attachments`, incluso nei backup del progetto.
