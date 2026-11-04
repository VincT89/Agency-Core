# Contratto n8n

## Scopo

Questo documento descrive il contratto tra Agency Core e n8n per generazione
dei post, callback, ticket e messaggi chatbot. Le richieste in ingresso passano
dal prefisso:

```text
/api/v1/integrations/n8n
```

Documento allineato al codice di `main_test`, base `4b5a7bb`, il 04/11/2026.
Il confronto riguarda rotte, middleware, validazione e payload applicativi;
non certifica la configurazione effettivamente attiva su Plesk o in n8n.
Nel progetto sono presenti test del contratto, dell'autenticazione e
dell'idempotenza.

Il dominio comunicato per la produzione è `https://r4.sodanoconsulting.it`.
La base delle API n8n su quel dominio è:

```text
https://r4.sodanoconsulting.it/api/v1/integrations/n8n
```

`APP_URL` deve contenere l'origine pubblica HTTPS, senza aggiungere `/api`.
Gli URL completi delle callback vengono generati dal gestionale e inviati nei
payload: il workflow deve riutilizzarli. In un ambiente diverso vanno usati il
dominio e le callback di quell'ambiente.

Gli ID, i testi e gli indirizzi di media negli esempi sono dimostrativi. I
segreti sono indicati soltanto con nomi di variabili o segnaposto e devono
essere configurati e condivisi separatamente, senza inserirli nel documento.
In produzione devono restare obbligatorie firma HMAC e idempotenza per le
richieste n8n verso Laravel.

## Sicurezza delle richieste n8n verso Laravel

Ogni richiesta, compreso il health check, richiede autenticazione e firma.
Per i payload JSON usare queste intestazioni:

```http
Authorization: Bearer <N8N_API_TOKEN>
Accept: application/json
Content-Type: application/json
X-N8N-Timestamp: <unix timestamp in secondi, 10 cifre>
X-N8N-Signature: sha256=<digest esadecimale>
Idempotency-Key: <chiave stabile del tentativo>
```

`Idempotency-Key` è obbligatoria per le richieste mutative, non per il GET
di health. `Content-Type` si riferisce alle richieste con body JSON. Token e
segreto di firma devono essere distinti, casuali e lunghi almeno 32 byte.

La firma è un HMAC SHA-256 calcolato con `N8N_SIGNING_SECRET` sui byte:

```text
<timestamp>\n<METHOD>\n<request-target>\n<raw request body>
```

- `METHOD` è il verbo HTTP maiuscolo.
- `request-target` comprende path e query string esattamente come inviati.
  Non comprende schema e dominio. Per il health check senza query vale
  `/api/v1/integrations/n8n/health`.
- I separatori `\n` rappresentano byte LF reali, non i due caratteri backslash
  e `n`. Non aggiungere un separatore dopo il body. Per un GET senza body,
  l'ultimo elemento è una stringa vuota.
- Il body va firmato prima di qualsiasi deserializzazione o nuova codifica.
  Deve essere esattamente la stringa inviata: spazi, ordine delle chiavi ed
  escaping diversi cambiano la firma.
- Il timestamp è in secondi, non millisecondi; lo scarto ammesso è di 300
  secondi per impostazione predefinita.
- Inviare il digest nella forma canonica `sha256=` seguita da 64 cifre
  esadecimali minuscole.
- Per compatibilità il server accetta anche il digest esadecimale senza
  prefisso e con lettere maiuscole o miste. Queste rappresentazioni vengono
  normalizzate e condividono lo stesso controllo anti-replay: cambiarne il
  formato non rende nuova una richiesta già ricevuta.
- Timestamp e firma devono essere rigenerati a ogni retry. La stessa firma
  già usata viene rifiutata con `409`, anche per il health check. Ripetendo
  stesso metodo, URL e body nello stesso secondo si ottiene la stessa firma:
  attendere un nuovo secondo prima di ritentare.
- La chiave di idempotenza deve restare uguale quando si ripete la stessa
  operazione logica.

Configurazione:

```dotenv
N8N_API_TOKEN=
N8N_SIGNING_SECRET=
N8N_REQUIRE_SIGNATURE=true
N8N_SIGNATURE_MAX_CLOCK_SKEW_SECONDS=300
N8N_REQUIRE_IDEMPOTENCY_KEY=true
N8N_IDEMPOTENCY_TTL_HOURS=48
N8N_IDEMPOTENCY_LOCK_SECONDS=600
N8N_IDEMPOTENCY_LOCK_WAIT_SECONDS=5
N8N_IDEMPOTENCY_IN_PROGRESS_TIMEOUT_MINUTES=30
N8N_CONNECT_TIMEOUT=5
N8N_HTTP_TIMEOUT=15
```

## Idempotenza

La chiave deve contenere da 8 a 255 caratteri ASCII visibili.

- Stessa chiave e stessa richiesta completata: Laravel restituisce la risposta
  memorizzata senza ripetere gli effetti, con header `X-Idempotent-Replay: true`.
- Il confronto usa metodo, URL con query e byte del body. Anche una diversa
  formattazione dello stesso JSON cambia la richiesta: conservarne la stringa
  originale durante i retry.
- Stessa chiave con metodo, route, query o payload diversi: risposta `409`.
- Richiesta ancora in lavorazione: viene rispettato il lock configurato;
  se il lock non è acquisibile entro l'attesa prevista, o la prenotazione
  risulta ancora attiva, la risposta è `409`.
- Prenotazione interrotta oltre la soglia: può essere recuperata soltanto per la
  stessa richiesta logica.
- La durata predefinita dei record è 48 ore. Le risposte restituite attraverso
  il middleware con codice inferiore a `500` possono essere memorizzate,
  compresi gli errori applicativi `4xx`. Le eccezioni e le risposte `5xx` non
  vengono mantenute come risposte completate.

Per ripetere una richiesta identica mantenere la stessa chiave, aggiornando
timestamp e firma. Se si correggono URL o body dopo un errore di validazione,
usare una nuova chiave per la richiesta corretta. Non cambiare chiave per
aggirare un'operazione ancora in corso o un esito incerto.

I record scaduti vengono rimossi dal comando pianificato
`system:prune-operational-logs`.

## Health check

```http
GET /api/v1/integrations/n8n/health
```

URL completo sul dominio comunicato:

```text
https://r4.sodanoconsulting.it/api/v1/integrations/n8n/health
```

Inviare il Bearer token, il timestamp e la firma calcolata sul body vuoto.
Non è richiesta una chiave di idempotenza.

Risposta attesa:

```json
{
  "ok": true,
  "provider": "n8n",
  "status": "ready"
}
```

Il risultato conferma autenticazione e raggiungibilità dell'applicazione, non
la salute completa dei singoli workflow n8n.

## Sicurezza e risposta dei webhook Laravel verso n8n

Per generazione, rigenerazione e messaggi il gestionale invia un `POST` JSON
al webhook configurato. Quando `N8N_API_TOKEN` è configurato, invia:

```http
Authorization: Bearer <N8N_API_TOKEN>
Content-Type: application/json
```

Il client attuale non aggiunge `X-N8N-Timestamp`, `X-N8N-Signature` o
`Idempotency-Key` alle richieste verso n8n. La firma HMAC descritta sopra
riguarda la direzione n8n verso Laravel, incluse tutte le callback. Configurare
il webhook n8n per verificare il Bearer token previsto dal gestionale.

n8n deve restituire un codice `2xx` quando accetta la richiesta. Il risultato
della generazione o della consegna viene comunicato successivamente tramite
la callback appropriata, non nel body della risposta al webhook. Il client
gestisce il body JSON della risposta, ma i flussi qui descritti non lo usano
come risultato finale. Il timeout di connessione predefinito è 5 secondi e
quello HTTP è 15 secondi: rispondere all'accettazione senza attendere una
generazione lunga.

La deduplicazione delle esecuzioni n8n deve usare `request_id` per i post e
`idempotency_key` del payload per i messaggi. Questi campi non sono header
HMAC o chiavi di idempotenza delle callback verso Laravel.

## Laravel verso n8n: generazione post

Webhook primario:

```dotenv
N8N_SUBMIT_MARKETING_CAMPAIGN_POST_WEBHOOK_URL=
```

Fallback:

```dotenv
N8N_GENERATE_SOCIAL_POST_WEBHOOK_URL=
```

Metodo applicativo: `N8nClient::submitMarketingCampaignPost()`.

Payload di riferimento:

```json
{
  "type": "marketing_campaign_post",
  "request_id": "identificatore-univoco",
  "campaign": {
    "id": 1,
    "name": "Campagna"
  },
  "client": {
    "id": 1,
    "name": "Cliente",
    "logo_url": null,
    "activity_description": null
  },
  "post": {
    "id": 1,
    "title": "Titolo",
    "description": "Indicazioni",
    "content_type": "post",
    "scheduled_date": null,
    "scheduled_time": null,
    "ai_analysis_enabled": true,
    "publishing_platforms": [],
    "media_count": 0,
    "primary_media_url": null,
    "primary_media_type": null,
    "media_items": [],
    "media": null
  },
  "callback_url": "https://r4.sodanoconsulting.it/api/v1/integrations/n8n/marketing-campaign-posts/1/versions",
  "failed_callback_url": "https://r4.sodanoconsulting.it/api/v1/integrations/n8n/marketing-campaign-posts/1/failed"
}
```

Campi presenti nel payload applicativo:

- `type` e `request_id`;
- `campaign.id` e `campaign.name`;
- `client.id` e `client.name`;
- `post.id`, `post.title`, `post.description`, `post.content_type`;
- `post.publishing_platforms`, `post.media_count`, `post.media_items`;
- `callback_url` e `failed_callback_url`.

Campi nullable:

- logo e descrizione attività del cliente;
- data e ora programmate;
- media principale;
- alias `post.media`.

`generation_type` è opzionale e viene aggiunto al livello principale soltanto
quando fornito all'azione di invio. Il payload iniziale non contiene un campo
`regeneration_type`: la callback deve comunque indicare esplicitamente il
tipo di risultato tra `full`, `caption` e `image`, rispettando le regole
descritte sotto.

Usare `post.media_items` come fonte principale. `post.media` è mantenuto come
alias del primo media per compatibilità; senza media è `null`.

Ogni elemento di `post.media_items` contiene `id`, `source`, `media_type`,
`url`, `mime_type`, `original_name`, `nextcloud_path`, `nextcloud_share_url`,
`nextcloud_file_id` e `sort_order`. I campi non pertinenti alla sorgente
possono essere null. L'array è ordinato e `sort_order` parte da zero.
Usare `url` per scaricare il file. Gli URL locali possono essere firmati e
temporanei: conservarli integralmente, inclusa la query string, e usarli entro
la scadenza. Non ricostruire gli indirizzi dai percorsi interni.

## Laravel verso n8n: rigenerazione

Webhook primario:

```dotenv
N8N_REGENERATE_SOCIAL_POST_WEBHOOK_URL=
```

Fallback: `N8N_GENERATE_SOCIAL_POST_WEBHOOK_URL`.

Metodo: `N8nClient::requestMarketingCampaignPostRegeneration()`.

Payload di riferimento:

```json
{
  "type": "marketing_campaign_post_regeneration",
  "post_id": 1,
  "request_id": "identificatore-univoco",
  "regeneration_type": "full",
  "prompt": null,
  "campaign": {
    "id": 1,
    "name": "Campagna"
  },
  "client": {
    "id": 1,
    "name": "Cliente",
    "logo_url": null,
    "activity_description": null
  },
  "post": {
    "id": 1,
    "title": "Titolo",
    "description": "Indicazioni",
    "content_type": "post",
    "publishing_platforms": [],
    "media_count": 0,
    "primary_media_url": null,
    "primary_media_type": null,
    "media_items": [],
    "media": null
  },
  "current_version": null,
  "callback_url": "https://r4.sodanoconsulting.it/api/v1/integrations/n8n/marketing-campaign-posts/1/versions",
  "failed_callback_url": "https://r4.sodanoconsulting.it/api/v1/integrations/n8n/marketing-campaign-posts/1/failed"
}
```

`regeneration_type` ammette:

- `full`: testo e immagini;
- `caption`: solo testo, conservando i media;
- `image`: solo media, conservando il testo.

`current_version`, quando presente, può includere `id`, `version_number`,
`title`, `caption`, `hashtags`, `image_url` e `image_urls`.

## Callback nuova versione

```http
POST /api/v1/integrations/n8n/marketing-campaign-posts/{post}/versions
```

Obbligatori:

- `request_id`;
- `regeneration_type`.

Regole condizionali:

- `full`: almeno `caption` e uno tra `image_url` e `image_urls`;
- `caption`: `caption`;
- `image`: uno tra `image_url` e `image_urls`.

Opzionali:

- `external_generation_id`;
- `title`;
- `hashtags`;
- `prompt_used`;
- `raw_payload`.

Alias accettati:

- caption: `caption`, poi `text`, `description`, `copy`;
- immagine singola: `image_url`, poi `media_url`, `url`;
- immagini multiple: `image_urls`, poi `images`;
- hashtag: array oppure stringa separata da virgole o spazi;
- `image_urls` o `images`: accettano anche una singola stringa URL, che viene
  convertita in un array.

È consigliato inviare tutti i campi al livello principale del JSON. I wrapper
`{ "data": {} }` e `{ "body": {} }` vengono normalizzati soltanto per
`request_id`, `regeneration_type`, caption e relativi alias, URL delle immagini
e relativi alias, e `hashtags`. Il result legacy normalizza anche `post_id`.
`external_generation_id`, `title`, `prompt_used` e `raw_payload` devono restare
al livello principale: se inseriti solo nel wrapper non vengono promossi tra
i campi validati.

Esempio dimostrativo di callback `full`; l'URL dell'immagine deve essere
sostituito con quello reale, raggiungibile dal server del gestionale:

```json
{
  "request_id": "identificatore-univoco",
  "regeneration_type": "full",
  "caption": "Testo generato per il post",
  "image_url": "https://example.com/output.jpg",
  "hashtags": ["esempio"]
}
```

`request_id` deve riprendere esattamente il valore ricevuto dal gestionale.
Inviare il risultato a `callback_url` con autenticazione, firma e una chiave
di idempotenza. Gli esiti della callback sono:

| HTTP | `status` | Significato |
| --- | --- | --- |
| `201` | `created` | Versione creata; `data` include `post_id`, `version_id` e `version_number`. |
| `200` | `duplicate` | Risultato già elaborato; nessuna seconda versione. |
| `200` | `ignored` | Risultato ignorato in base allo stato del post. |
| `409` | `conflict` | Richiesta incompatibile, ad esempio `request_id` diverso o già usato per un altro post. |

Un replay della stessa chiave di idempotenza restituisce invece l'HTTP e il
body originali: può quindi restituire ancora `201` con l'header
`X-Idempotent-Replay: true`, senza creare una seconda versione.

## Callback result legacy

```http
POST /api/v1/integrations/n8n/marketing-campaign-posts/result
```

Richiede `post_id`, `request_id`, `regeneration_type` e applica le stesse
regole condizionali della callback versionata. L'endpoint resta disponibile per
retrocompatibilità; i nuovi workflow dovrebbero preferire la route legata al
post.

## Callback di fallimento

```http
POST /api/v1/integrations/n8n/marketing-campaign-posts/{post}/failed
```

Obbligatori:

- `request_id`;
- `error`.

Se `request_id` non coincide con la generazione attiva, la richiesta viene
rifiutata con `400`. Con una corrispondenza valida la callback modifica solo
post in stato `pending_n8n`, `submitted_to_n8n` o `regenerating`: ripristina
lo stato precedente memorizzato e registra errore e completamento. Se lo stato
precedente manca oppure è ancora uno di questi stati transitori, usa `draft`.
Se l'elaborazione è già conclusa risponde `200` con `status: ignored` senza
modificare il post. Una callback elaborata risponde `200` con
`status: success`.

Inviare a `failed_callback_url`, con le stesse regole di autenticazione e
idempotenza, un payload al livello principale:

```json
{
  "request_id": "identificatore-univoco",
  "error": "Generazione non completata entro il tempo previsto"
}
```

Questo comportamento descrive la callback `/failed`; il ripristino eseguito
dal job Laravel in caso di mancato invio del webhook è un percorso distinto.

Non inviare stack trace, token o payload contenenti segreti nel campo `error`.

## Creazione ticket da n8n

```http
POST /api/v1/integrations/n8n/tickets
```

Obbligatori:

- uno tra `client_id` e `project_id`;
- `source`: `whatsapp`, `n8n`, `email` o `manual`;
- `n8n_execution_id`.

Opzionali:

- `title`, con default `Ticket WhatsApp`;
- `description`;
- `priority`: `low`, `medium`, `high`, `urgent`;
- `context`.

La coppia `source` e `n8n_execution_id` evita la creazione duplicata del
ticket. Se si inviano sia `client_id` sia `project_id`, il progetto deve
appartenere a quel cliente; altrimenti la validazione risponde `422`.
`context`, quando presente, è un oggetto JSON; `context.original_message`
viene usato come descrizione se `description` non è fornita.

Esempio dimostrativo, con un `client_id` da sostituire con uno esistente:

```json
{
  "client_id": 1,
  "source": "n8n",
  "n8n_execution_id": "esecuzione-dimostrativa-1",
  "title": "Richiesta del cliente",
  "description": "Contenuto della richiesta",
  "priority": "medium"
}
```

La risposta è `201` per un nuovo ticket o `200` per un ticket già presente.
Il body contiene `success: true` e `data` con `ticket_id`, `created`, `status`,
`client_id` e `project_id`.

## Messaggio cliente chatbot

```http
POST /api/v1/integrations/n8n/chatbot/client-message
```

Obbligatori:

- uno tra `client_id` e `phone`;
- `session_type`: `marketing` o `ticket`;
- `session_id`;
- `message`;
- `type`: `comment`, `approval` o `change_request`.

Regole:

- se `client_id` e `phone` sono entrambi presenti devono identificare lo stesso
  cliente;
- un telefono non riconosciuto produce `404`;
- `session_id` è l'ID del post per `marketing` o del ticket per `ticket`, non
  l'ID della riga di `chatbot_client_sessions`;
- il post o ticket deve già essere presente nel relativo contesto chatbot
  dello stesso cliente; altrimenti la risposta è `404`.

Esempio dimostrativo di messaggio relativo a un ticket già sincronizzato:

```json
{
  "client_id": 1,
  "session_type": "ticket",
  "session_id": 1,
  "message": "Grazie, ho ricevuto la risposta",
  "type": "comment"
}
```

La risposta di successo è `200` con `success: true`. Per i post, `approval`
e `change_request` aggiornano anche lo stato del post; sui ticket vengono
registrati come messaggi del cliente senza applicare questi stati al ticket.

## Laravel verso n8n: messaggi al cliente

Webhook dedicato, senza fallback:

```dotenv
N8N_CHATBOT_OUTGOING_MESSAGE_WEBHOOK_URL=
```

Metodo applicativo: `N8nClient::sendChatbotOutgoingMessage()`. Il flusso di
invio attuale parte dai commenti dei ticket inviati tramite Sody. Payload di
riferimento con ID e testo dimostrativi:

```json
{
  "event": "chatbot_outgoing_message",
  "message_id": "ticket_comment_1",
  "client_id": 1,
  "session_type": "ticket",
  "session_id": 1,
  "message": "Risposta dell'operatore",
  "source": "operator",
  "idempotency_key": "ticket_1_comment_1",
  "callback_url": "https://r4.sodanoconsulting.it/api/v1/integrations/n8n/chatbot/outgoing-messages/ticket_comment_1/status"
}
```

`phone` viene aggiunto solo se il cliente ha un numero di telefono valorizzato.
Il workflow deve deduplicare l'invio tramite `idempotency_key`, accettare il
webhook con risposta `2xx` e comunicare successivamente l'esito della consegna
a `callback_url`. Il campo `idempotency_key` del body non sostituisce l'header
`Idempotency-Key` richiesto nella callback verso Laravel.

## Stato dei messaggi in uscita

```http
POST /api/v1/integrations/n8n/chatbot/outgoing-messages/{messageId}/status
```

Formati supportati:

- `ticket_comment_{id}`;
- `task_comment_{id}`.

Richiede `status` uguale a `sent` o `failed`. Può includere
`external_message_id` ed `error`.

Il commento deve esistere, avere canale `sody` ed essere ancora in stato
`pending` o `processing`. Ripetere lo stesso esito restituisce `200` con
`idempotent: true`. Passare da un esito finale a uno diverso viene rifiutato
con `400`; anche formato del message ID o canale errato producono `400`.
Un commento inesistente produce `404`.

Esempio di consegna riuscita:

```json
{
  "status": "sent",
  "external_message_id": "messaggio-dimostrativo-1"
}
```

Per una consegna fallita inviare `status: failed` e, se disponibile, una
descrizione sintetica in `error`, priva di segreti. La route accetta anche
`task_comment_{id}` per commenti di task compatibili; questo non implica che
il flusso di invio ticket descritto sopra generi automaticamente messaggi
per le task.

## Retry n8n

Per una richiesta Laravel verso n8n:

- riutilizzare lo stesso `request_id` quando si ripete la medesima generazione;
- non creare due esecuzioni concorrenti per lo stesso post e richiesta;
- usare timeout coerenti con `N8N_CONNECT_TIMEOUT` e `N8N_HTTP_TIMEOUT`;
- classificare come ambiguo un timeout avvenuto dopo l'accettazione del webhook;
- verificare prima se n8n ha già completato il lavoro.

I job di generazione e rigenerazione prevedono fino a tre tentativi e
configurano backoff `[10, 60, 300]` secondi. Con un driver asincrono serve un
worker di coda attivo; evitare che ogni retry avvii una nuova generazione n8n.

Per una callback n8n verso Laravel:

- riutilizzare la stessa `Idempotency-Key` per lo stesso tentativo logico;
- rigenerare timestamp e firma senza riutilizzare una firma già inviata;
- applicare backoff su timeout, `429` e `5xx`;
- non cambiare payload mantenendo la stessa chiave.

Un `409` richiede di leggere il motivo: per una firma già usata rigenerare il
timestamp e la firma; per una richiesta in corso attendere; per payload
incompatibile o `request_id` errato correggere il flusso senza ritentare alla
cieca. Un `503` con messaggio di configurazione dell'integrazione richiede
la correzione della configurazione, non soltanto un retry. Se la risposta
include `X-Idempotent-Replay: true`, è un esito memorizzato: ripetere la stessa
richiesta non ne cambia il contenuto.

## Checklist produzione

- `APP_URL` pubblico e HTTPS, senza suffisso `/api`.
- Token e signing secret distinti e robusti, conservati fuori dal documento.
- Firma e idempotenza obbligatorie per le richieste verso Laravel.
- Verifica del Bearer token sui webhook n8n chiamati dal gestionale.
- Orologi di Laravel e n8n sincronizzati.
- Webhook di produzione configurati per generazione, rigenerazione e messaggi;
  i due webhook dei post possono usare il fallback configurato.
- Callback riprese dai payload e appartenenti all'ambiente corretto.
- Test health con header validi.
- Test di replay della stessa callback con stessa chiave e firma rinnovata.
- Test di rifiuto per firma errata, timestamp scaduto e payload cambiato.
- Controllo dei log senza segreti.
- Worker di coda attivo quando si usa un driver asincrono.
- Scheduler attivo per la pulizia dei record scaduti.

## Diagnostica

- `400`: chiave di idempotenza mancante o non valida, `request_id` errato sulla
  callback `/failed`, oppure message ID, canale o stato non aggiornabile nella
  callback di consegna.
- `401`: Bearer token mancante.
- `403`: token non valido oppure firma mancante, errata o scaduta.
- `404`: risorsa inesistente, telefono non riconosciuto oppure post o ticket
  non presente nel contesto chatbot di quel cliente.
- `409`: firma già usata, chiave riutilizzata per una richiesta diversa,
  richiesta ancora in corso, conflitto del `request_id` sulla callback di
  versione oppure identità cliente incoerente. Leggere `message` o `reason`.
- `422`: payload non conforme o campi condizionali mancanti.
- `429`: limite superato; le rotte hanno un limite configurato di 120
  richieste al minuto. Applicare backoff e rispettare `Retry-After` se presente.
- `503` con messaggio relativo all'integrazione: token troppo corto quando la
  firma è obbligatoria, signing secret mancante quando richiesto, oppure
  signing secret troppo corto. Correggere configurazione e relativa cache.
- Altri `5xx`: verificare il guasto dell'applicazione o dei servizi richiesti;
  applicare backoff per gli errori temporanei e conservare ID richiesta e
  correlazione senza esporre dati sensibili.

Usare inoltre:

```bash
php artisan monitor:system
php artisan system:prune-operational-logs --dry-run
```
