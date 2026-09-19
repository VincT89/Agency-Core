# Esempi commerciali e finanziari locali

Gli esempi sono riconoscibili dal prefisso **DEMO**. Nomi, importi e documenti sono dimostrativi e non rappresentano operazioni reali.

Il caricamento comprende tre clienti assegnati al Commerciale, sei offerte nei quattro stati (anche una revisione), un progetto collegato a un'offerta accettata e tre task. Sono presenti cinque fatture gestionali nei diversi stati di incasso, due pagamenti, sei ricorrenze con scadenze fino a 24 mesi, spese singole, sei documenti e sei entrate manuali. Alcune uscite future sono senza documento; quelle pagate hanno il giustificativo collegato. Un documento resta disponibile per provare l'associazione a una spesa.

Le ricorrenze mostrano frequenze mensili, trimestrali, semestrali e annuali, oltre a una ricorrenza settimanale sospesa. Gli allegati includono un breve testo nell'offerta fotografica e materiali nell'archivio del cliente Marketing.

Se manca un saldo iniziale viene inserito un saldo **dimostrativo di 15.000 euro**, con decorrenza dall'inizio del mese. Un saldo già configurato resta invariato. Gli importi delle previsioni comprendono anche gli esempi: non sono utilizzabili come situazione contabile reale.

## Caricamento esplicito

Dopo avere applicato le migrazioni, soltanto in un ambiente locale:

```sh
php artisan db:seed --class=LocalCommercialFinanceDemoSeeder
```

La procedura richiede Admin e Commerciale già esistenti e non crea né modifica account. Preferisce il Commerciale `commerciale@sodanoconsulting.it`, se disponibile. Non è collegata al seeder generale e rifiuta ambienti di produzione o database remoti. Gli eventi dei modelli restano disattivati durante l'inserimento, quindi non genera notifiche o invii esterni. Le fatture mantengono lo stato fiscale «Da preparare», senza numeri fiscali o trasmissioni.

Un secondo avvio trova gli esempi già presenti e non li duplica o modifica. Le righe preesistenti non vengono riscritte. Non utilizzare questa procedura per caricare dati in produzione.
