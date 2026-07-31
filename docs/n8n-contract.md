# Contratto n8n

## Scopo

Questo documento descrive il contratto tra Agency Core e n8n per generazione
dei post, callback, ticket e messaggi chatbot. Le richieste in ingresso passano
dal prefisso:

```text
/api/v1/integrations/n8n
```

Il contratto è implementato e coperto da test. In produzione devono restare
obbligatorie firma HMAC e idempotenza.

## Sicurezza delle richieste n8n verso Laravel

Ogni richiesta richiede:

```http
Authorization: Bearer <N8N_API_TOKEN>
X-N8N-Timestamp: <unix timestamp>
X-N8N-Signature: sha256=<digest esadecimale>
Idempotency-Key: <chiave stabile del tentativo>
```

`Idempotency-Key` è obbligatoria per le richieste mutative. Token e segreto di
firma devono essere distinti, casuali e lunghi almeno 32 byte.

La firma è un HMAC SHA-256 calcolato con `N8N_SIGNING_SECRET` sui byte:

```text
<timestamp>\n<METHOD>\n<request-target>\n<raw request body>
```

- `METHOD` è il verbo HTTP maiuscolo.
- `request-target` comprende path e query string esattamente come inviati.
- Il body va firmato prima di qualsiasi deserializzazione o nuova codifica.
- Timestamp e firma devono essere rigenerati a ogni retry.
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
  memorizzata senza ripetere gli effetti.
- Stessa chiave con metodo, route, query o payload diversi: risposta `409`.
- Richiesta ancora in lavorazione: viene rispettato il lock configurato.
- Prenotazione interrotta oltre la soglia: può essere recuperata soltanto per la
  stessa richiesta logica.

I record scaduti vengono rimossi dal comando pianificato
`system:prune-operational-logs`.

## Health check

```http
GET /api/v1/integrations/n8n/health
```

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
    "media": {}
  },
  "callback_url": "https://gestionale.example.it/api/v1/integrations/n8n/..."
}
```

Campi sempre richiesti dal workflow:

- `type` e `request_id`;
- `campaign.id` e `campaign.name`;
- `client.id` e `client.name`;
- `post.id`, `post.title`, `post.description`, `post.content_type`;
- `post.publishing_platforms`, `post.media_count`, `post.media_items`;
- `callback_url`.

Campi nullable:

- logo e descrizione attività del cliente;
- data e ora programmate;
- media principale;
- alias `post.media`;
- `generation_type`.

Usare `post.media_items` come fonte principale. `post.media` è mantenuto come
alias del primo media per compatibilità.

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
    "media": {}
  },
  "current_version": null,
  "callback_url": "https://gestionale.example.it/api/v1/integrations/n8n/..."
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
- hashtag: array oppure stringa CSV;
- wrapper: `{ "data": {} }` oppure `{ "body": {} }`.

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
rifiutata. Con una corrispondenza valida il post torna allo stato precedente
memorizzato, oppure a `generated`, e vengono registrati errore e completamento.

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
ticket.

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
- `session_id` deve corrispondere a una sessione esistente del tipo dichiarato.

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
aggiornabile. Ripetere lo stesso esito è idempotente.

## Retry n8n

Per una richiesta Laravel verso n8n:

- riutilizzare lo stesso `request_id` quando si ripete la medesima generazione;
- non creare due esecuzioni concorrenti per lo stesso post e richiesta;
- usare timeout coerenti con `N8N_CONNECT_TIMEOUT` e `N8N_HTTP_TIMEOUT`;
- classificare come ambiguo un timeout avvenuto dopo l'accettazione del webhook;
- verificare prima se n8n ha già completato il lavoro.

Per una callback n8n verso Laravel:

- riutilizzare la stessa `Idempotency-Key` per lo stesso tentativo logico;
- rigenerare timestamp e firma;
- applicare backoff su timeout, `429` e `5xx`;
- non cambiare payload mantenendo la stessa chiave.

## Checklist produzione

1. `APP_URL` pubblico e HTTPS.
2. Token e signing secret distinti e robusti.
3. Firma e idempotenza obbligatorie.
4. Orologi di Laravel e n8n sincronizzati.
5. Webhook separati per generazione, rigenerazione e messaggi.
6. Callback configurate con gli URL dell'ambiente corretto.
7. Test health con header validi.
8. Test di replay della stessa callback.
9. Test di rifiuto per firma errata, timestamp scaduto e payload cambiato.
10. Controllo dei log senza segreti.
11. Scheduler attivo per la pulizia dei record scaduti.

## Diagnostica

- `401` o `403`: token, firma, formato dell'header o configurazione cache.
- `409`: chiave riutilizzata per una richiesta diversa oppure identità cliente
  incoerente.
- `422`: payload non conforme o campi condizionali mancanti.
- `429`: limite superato; applicare backoff.
- `5xx`: errore temporaneo dell'applicazione; conservare ID richiesta e
  correlazione senza esporre dati sensibili.

Usare inoltre:

```bash
php artisan monitor:system
php artisan system:prune-operational-logs --dry-run
```
