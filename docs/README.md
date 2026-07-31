# Documentazione Agency Core

Questa cartella descrive il comportamento verificato del gestionale. Il codice,
le migrazioni e le policy restano la fonte definitiva quando una guida e
l'implementazione divergono.

## Guide

| Documento | Contenuto |
| --- | --- |
| [Ambito funzionale](functional-scope.md) | Moduli, ruoli, flussi coperti e limiti attuali. |
| [Distribuzione in produzione](production-deployment.md) | Preparazione, rilascio, worker, scheduler, controlli e rollback. |
| [Pulizia dati](data-cleanup.md) | Procedura prudente per rimuovere i dati dimostrativi senza perdere accessi o configurazioni. |
| [Nextcloud](nextcloud.md) | Configurazione, creazione cartelle cliente, accesso ai media e limiti di cancellazione. |
| [Shooting](shooting-workflow.md) | Flusso tra marketing, fotografo e contatto esterno del cliente. |
| [Pubblicazione social](social-production-readiness.md) | Architettura, Meta, kill switch, collaudo, monitoraggio e gestione errori. |
| [Contratto n8n](n8n-contract.md) | Autenticazione, firma, idempotenza, endpoint e payload. |
| [Fatturazione elettronica e Aruba](electronic-invoicing-aruba.md) | Perimetro supportato, configurazione, sicurezza, callback e collaudo Aruba/SdI. |

## Significato degli stati

- `Pronto`: implementato e coperto da verifiche locali; può comunque dipendere
  dalla corretta configurazione dell'ambiente.
- `Pronto lato codice`: implementazione presente, ma manca il collaudo con il
  servizio esterno reale.
- `Fondazione pronta`: modello, interfaccia e regole interne sono presenti, ma
  manca ancora una parte necessaria del flusso completo.
- `Non implementato`: non basta aggiungere credenziali; occorre sviluppare il
  relativo componente.

## Regole di manutenzione

Quando cambia un'integrazione o un flusso:

1. aggiornare prima i test e il codice;
2. aggiornare la guida specifica e il riepilogo nel `README.md`;
3. distinguere sempre ambiente DEMO, staging e produzione;
4. non inserire esempi contenenti credenziali reali;
5. datare i risultati dei collaudi esterni, perché autorizzazioni e API possono
   cambiare indipendentemente dal repository.

Ultimo riallineamento con il codice: 31 luglio 2026.
