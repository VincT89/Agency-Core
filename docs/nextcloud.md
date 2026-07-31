# Integrazione Nextcloud

## Stato

L'integrazione è implementata tramite WebDAV e OCS Share API. In produzione
deve usare HTTPS e credenziali dedicate. La creazione di un cliente richiede un
nome cartella Nextcloud valido.

## Configurazione

```dotenv
NEXTCLOUD_BASE_URL=https://cloud.example.it
NEXTCLOUD_USERNAME=utente_applicativo
NEXTCLOUD_PASSWORD=segreto_o_password_app
NEXTCLOUD_WEBDAV_PATH=/remote.php/dav/files
NEXTCLOUD_PHOTOS_ROOT=/FotoClienti
NEXTCLOUD_VIDEOS_ROOT=/VideoClienti
NEXTCLOUD_SHARE_EXPIRE_DAYS=7
NEXTCLOUD_CONNECT_TIMEOUT=5
NEXTCLOUD_REQUEST_TIMEOUT=15
NEXTCLOUD_STREAM_TIMEOUT=300
NEXTCLOUD_STREAM_READ_TIMEOUT=30
```

Se l'account usa autenticazione a due fattori o policy esterne, utilizzare una
password applicativa compatibile con WebDAV. Non usare un account personale se
è possibile creare un'utenza tecnica con accesso limitato alle radici previste.

## Creazione del cliente

Il campo `nextcloud_folder_name`:

- è obbligatorio;
- può contenere soltanto caratteri compatibili con `alpha_dash`;
- non può contenere slash, backslash o `..`;
- deve essere univoco tra i clienti.

Per un valore `acme`, il gestionale assicura l'esistenza di:

```text
/FotoClienti/acme
/VideoClienti/acme
```

Le radici dipendono dalla configurazione. Le directory mancanti vengono create
ricorsivamente tramite WebDAV.

Se una delle due directory non può essere creata, il cliente non viene salvato
e l'interfaccia mostra un errore comprensibile. Essendo Nextcloud un sistema
esterno, un fallimento dopo la prima creazione può lasciare una directory
parziale da controllare manualmente.

Nel database viene salvato il percorso foto; il percorso video viene derivato
dalla radice video e dal nome cartella del cliente.

## Modifica e cancellazione

- Il collegamento Nextcloud non può essere semplicemente svuotato dopo essere
  stato impostato.
- Cambiare il nome predispone le nuove directory, ma non sposta né elimina i
  file presenti nelle vecchie.
- Eliminare un cliente dal gestionale non elimina automaticamente le cartelle
  remote.
- Le cancellazioni remote devono essere separate, inventariate e autorizzate.

Questi vincoli evitano che una modifica dell'anagrafica distrugga per errore
materiale fotografico.

## Uso dei media

Il servizio permette di:

- elencare directory e file immagine o video;
- leggere metadati, dimensione, MIME type, ETag e identificativo Nextcloud;
- mostrare anteprime e scaricare file attraverso route autenticate;
- creare condivisioni pubbliche temporanee quando necessarie ai provider;
- revocare le condivisioni dopo l'uso;
- generare URL firmati per limitare l'accesso ai media consegnati.

Gli utenti non amministrativi possono accedere soltanto ai percorsi compresi
nelle radici configurate e collegati ai clienti visibili nel loro perimetro.

## Pubblicazione social

Quando una versione usa un media Nextcloud, la preparazione della pubblicazione:

1. verifica l'esistenza del file;
2. legge metadati ed ETag;
3. congela nel payload lo stato verificato;
4. crea, se necessario, una condivisione temporanea;
5. consegna il media al provider;
6. revoca la condivisione secondo il flusso previsto.

Un file rimosso o modificato dopo la preparazione può bloccare la pubblicazione
per proteggere l'integrità dello snapshot.

## Collaudo

Prima del go-live verificare con un cliente di prova controllato:

1. creazione simultanea delle directory foto e video;
2. visualizzazione di un'immagine;
3. download di un video;
4. rifiuto di un percorso fuori dalle radici consentite;
5. creazione e revoca di una condivisione temporanea;
6. messaggio chiaro quando Nextcloud non è raggiungibile;
7. assenza di credenziali o percorsi completi sensibili nei log.

## Riferimenti ufficiali

- [Nextcloud WebDAV: operazioni su file e cartelle](https://docs.nextcloud.com/server/stable/developer_manual/client_apis/WebDAV/basic.html)
- [Nextcloud OCS Share API](https://docs.nextcloud.com/server/stable/developer_manual/client_apis/OCS/ocs-share-api.html)
