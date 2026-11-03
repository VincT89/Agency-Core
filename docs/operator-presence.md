# Presenza degli operatori

La dashboard amministrativa mostra la disponibilità confermata rispetto alle fasce personali. Gli amministratori, compresi i developer con accesso amministrativo, vedono tutti gli operatori attivi. Gli altri utenti vedono soltanto i propri comandi e il proprio storico in **Le mie disponibilità**. Lo storico del team si trova in **Disponibilità team**, con la selezione della settimana e dell’utente.

## Funzionamento

All’autenticazione viene registrato l’accesso nel registro già esistente, anche quando avviene tramite la funzione di accesso ricordato. La dashboard admin mostra **Ultimo accesso** separatamente da **Disponibile dalle**. L’accesso al gestionale non conferma automaticamente la disponibilità.

Dopo il login compare subito **Sei disponibile?**. Durante una propria fascia l’operatore può premere **Sono disponibile**: il conteggio parte da quella conferma, con l’orario del server. **Non ancora**, oppure Escape, chiudono la domanda senza avviare alcun conteggio e senza riproporla a ogni navigazione. Il comando ordinario resta disponibile nel riquadro. Un nuovo login ripropone la domanda.

Prima della prossima fascia della giornata compare **Sono disponibile ora**, sia nella domanda al login sia nel riquadro: la conferma avvia immediatamente il conteggio e l’anticipo viene incluso nel tempo oltre fascia. Per esempio, con fascia 09:00–13:00 e conferma alle 08:00, dalle 08:00 alle 09:00 si conta tempo oltre fascia, dalle 09:00 tempo ordinario. Le pause e i periodi senza collegamento restano esclusi; non viene recuperato il tempo prima della conferma. Si può anticipare solo la prima fascia ancora disponibile di oggi, senza saltarne una già in corso. Se non sono rimaste fasce per oggi, la domanda permette di aprire **Le mie disponibilità**.

La presenza resta visibile anche navigando nelle altre pagine del gestionale. **Pausa** sospende il conteggio; **Riprendi disponibilità** lo riattiva. **Termina disponibilità** oppure il logout della sessione che ha confermato la presenza registrano l’uscita. Questa modifica al login riutilizza il registro accessi e non richiede nuove migrazioni.

Alla fine della fascia il conteggio si arresta. **Resto disponibile** avvia il tempo oltre fascia dal momento della conferma: il tempo trascorso in attesa non viene recuperato. Le pause sono escluse sia dalla disponibilità complessiva sia dal tempo aggiuntivo. Quest’ultimo resta distinto dall’orario di ingresso: un ingresso posticipato non viene compensato nascondendolo nel totale.

La conferma anticipata non autorizza automaticamente il prolungamento dopo la fine della fascia: anche in questo caso è necessario premere **Resto disponibile**.

Il prolungamento termina al più tardi alla fascia successiva o alla mezzanotte locale; serve una nuova conferma per una nuova fascia. Le fasce di riferimento vengono copiate nello storico all’ingresso: modificarle o cancellarle successivamente non riscrive gli intervalli già registrati. Il calendario mantiene le sue regole di modifica esistenti; le fasce senza alcuna presenza confermata restano dati del calendario e non costituiscono un registro immutabile delle assenze.

## Collegamento e conteggi

La pagina invia un segnale ogni 20 secondi. Dopo 120 secondi senza segnali la presenza è indicata come **Connessione persa**. Il conteggio si ferma all’ultimo segnale ricevuto, senza aggiungere i due minuti di attesa. Tale orario viene esplicitamente mostrato come ultimo segnale, non come logout certo. La ripresa dopo una disconnessione richiede una nuova conferma.

Più schede della stessa sessione non creano presenze duplicate. Un’altra sessione dello stesso utente non può rinnovare automaticamente la presenza: l’utente può terminarla esplicitamente e confermarne una nuova. Non sono registrati tasti, movimenti del mouse, schermate, siti visitati o indirizzi IP. Lo stato verde indica disponibilità dichiarata e collegamento recente, non prova continua dell’attività lavorativa.

Date e fasce sono visualizzate nel fuso del gestionale, attualmente Europe/Rome. Gli istanti e i conteggi usano secondi UTC. I totali sono mostrati in ore e minuti, arrotondando per difetto i secondi residui.

Lo storico raggruppa le presenze per operatore e giorno: ogni riga mostra il primo ingresso, l’ultima uscita e i totali di disponibilità, pause e tempo oltre fascia. Lo stato si riferisce alla presenza più recente del giorno. Il dettaglio delle fasce conserva tutti gli ingressi, le uscite e gli intervalli, anche in caso di più collegamenti nella stessa fascia. I totali sommano i secondi confermati prima dell’arrotondamento; le ore tra una fascia e l’altra non vengono aggiunte. La paginazione mantiene sempre insieme tutte le presenze della giornata.

## Rilascio

Per la domanda al login e la visualizzazione dell’accesso non servono nuove migrazioni. Dopo il rilascio eseguire `php artisan optimize:clear` per ricaricare anche la registrazione degli eventi del login e compilare gli asset con la normale procedura del progetto.

Le tabelle delle presenze e degli intervalli sono create dalla migrazione di base `2026_11_02_100000_create_user_presence_tables.php`. Se non è già stata applicata, dopo il deploy eseguire `php artisan migrate --force` con il PHP corretto del sito. Sono necessari backup e normale verifica delle migrazioni prima dell’esecuzione in produzione.

Lo scheduler Laravel esegue `presence:expire` ogni minuto. Serve che il normale cron `schedule:run` del gestionale sia attivo; non occorre un nuovo cron dedicato. Dashboard e comandi riconoscono comunque le presenze scadute anche prima della pulizia dello scheduler. Il processo di pulizia rende definitiva la chiusura nello storico. La rimozione delle due tabelle tramite rollback elimina lo storico delle presenze; non eseguire rollback per annullare soltanto modifiche grafiche.
