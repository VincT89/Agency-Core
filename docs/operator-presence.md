# Presenza degli operatori

La dashboard amministrativa mostra la disponibilità confermata rispetto alle fasce personali. Gli amministratori, compresi i developer con accesso amministrativo, vedono tutti gli operatori attivi. Gli altri utenti vedono soltanto i propri comandi e il proprio storico in **Le mie disponibilità**. Lo storico del team si trova in **Disponibilità team**, con la selezione della settimana e dell’utente.

## Funzionamento

L’operatore preme **Sono disponibile** durante una propria fascia. L’ingresso viene registrato con l’orario del server. La presenza resta visibile anche navigando nelle altre pagine del gestionale. **Pausa** sospende il conteggio; **Riprendi disponibilità** lo riattiva. **Termina disponibilità** oppure il logout della sessione che ha confermato la presenza registrano l’uscita.

Alla fine della fascia il conteggio si arresta. **Resto disponibile** avvia il tempo oltre fascia dal momento della conferma: il tempo trascorso in attesa non viene recuperato. Le pause sono escluse sia dalla disponibilità complessiva sia dal tempo aggiuntivo. Quest’ultimo resta distinto dall’orario di ingresso: un ingresso posticipato non viene compensato nascondendolo nel totale.

Il prolungamento termina al più tardi alla fascia successiva o alla mezzanotte locale; serve una nuova conferma per una nuova fascia. Le fasce di riferimento vengono copiate nello storico all’ingresso: modificarle o cancellarle successivamente non riscrive gli intervalli già registrati. Il calendario mantiene le sue regole di modifica esistenti; le fasce senza alcuna presenza confermata restano dati del calendario e non costituiscono un registro immutabile delle assenze.

## Collegamento e conteggi

La pagina invia un segnale ogni 20 secondi. Dopo 120 secondi senza segnali la presenza è indicata come **Connessione persa**. Il conteggio si ferma all’ultimo segnale ricevuto, senza aggiungere i due minuti di attesa. Tale orario viene esplicitamente mostrato come ultimo segnale, non come logout certo. La ripresa dopo una disconnessione richiede una nuova conferma.

Più schede della stessa sessione non creano presenze duplicate. Un’altra sessione dello stesso utente non può rinnovare automaticamente la presenza: l’utente può terminarla esplicitamente e confermarne una nuova. Non sono registrati tasti, movimenti del mouse, schermate, siti visitati o indirizzi IP. Lo stato verde indica disponibilità dichiarata e collegamento recente, non prova continua dell’attività lavorativa.

Date e fasce sono visualizzate nel fuso del gestionale, attualmente Europe/Rome. Gli istanti e i conteggi usano secondi UTC. I totali sono mostrati in ore e minuti, arrotondando per difetto i secondi residui.

## Rilascio

Questa funzionalità richiede la nuova migrazione `2026_11_02_100000_create_user_presence_tables.php`, che aggiunge soltanto le tabelle delle presenze e degli intervalli. Dopo il deploy eseguire `php artisan migrate --force` con il PHP corretto del sito e compilare gli asset con la procedura di rilascio del progetto. Sono necessari backup e normale verifica delle migrazioni prima dell’esecuzione in produzione.

Lo scheduler Laravel esegue `presence:expire` ogni minuto. Serve che il normale cron `schedule:run` del gestionale sia attivo; non occorre un nuovo cron dedicato. Dashboard e comandi riconoscono comunque le presenze scadute anche prima della pulizia dello scheduler. Il processo di pulizia rende definitiva la chiusura nello storico. La rimozione delle due tabelle tramite rollback elimina lo storico delle presenze; non eseguire rollback per annullare soltanto modifiche grafiche.
