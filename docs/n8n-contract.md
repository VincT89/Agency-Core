# Contratto n8n

Questo documento descrive il contratto API e i payload scambiati tra Laravel (Agency Core) e n8n, bloccando lo stato attuale per i test di integrazione e garantendo retrocompatibilità.

## 1. Auth inbound n8n → Laravel

Tutte le API in ingresso (da n8n verso Laravel) richiedono autenticazione tramite header:
`Authorization: Bearer <N8N_API_TOKEN>`

- **Configurazione Reale:** `services.n8n.token`
- **Variabile d'ambiente:** `N8N_API_TOKEN`

## 2. Laravel → n8n: Generazione post marketing (Outbound)

- **Metodo:** `N8nClient::submitMarketingCampaignPost()`
- **Action:** `SubmitMarketingCampaignPostToN8nAction`
- **Webhook primario:** `N8N_SUBMIT_MARKETING_CAMPAIGN_POST_WEBHOOK_URL`
- **Fallback:** `N8N_GENERATE_SOCIAL_POST_WEBHOOK_URL`

### Payload Obbligatorio di Fatto
I seguenti campi vengono sempre costruiti dal codice e inviati (anche se alcuni valori sono null):

```json
{
  "type": "marketing_campaign_post",
  "request_id": "...",
  "campaign": {
    "id": 1,
    "name": "..."
  },
  "client": {
    "id": 1,
    "name": "...",
    "logo_url": null,
    "activity_description": null
  },
  "post": {
    "id": 1,
    "title": "...",
    "description": "...",
    "content_type": "...",
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
  "callback_url": "..."
}
```

### Obbligatori per n8n
- `type`
- `request_id`
- `campaign.id`
- `campaign.name`
- `client.id`
- `client.name`
- `post.id`
- `post.title`
- `post.description`
- `post.content_type`
- `post.publishing_platforms`
- `post.media_count`
- `post.media_items`
- `callback_url`

### Opzionali / nullable
- `client.logo_url`
- `client.activity_description`
- `post.scheduled_date`
- `post.scheduled_time`
- `post.primary_media_url`
- `post.primary_media_type`
- `post.media`
- `generation_type`

### Preferenze payload media
Preferire `post.media_items` rispetto a `post.media`. Quest'ultimo è solo un alias/fallback del primo media. Per il media principale, i campi `post.primary_media_url` e `post.primary_media_type` sono comodi ma non vanno trattati come unica fonte.

## 3. Laravel → n8n: Rigenerazione post (Outbound)

- **Metodo:** `N8nClient::requestMarketingCampaignPostRegeneration()`
- **Action:** `RequestMarketingCampaignPostRegenerationAction`
- **Webhook primario:** `N8N_REGENERATE_SOCIAL_POST_WEBHOOK_URL`
- **Fallback:** `N8N_GENERATE_SOCIAL_POST_WEBHOOK_URL`

### Payload Obbligatorio di Fatto
```json
{
  "type": "marketing_campaign_post_regeneration",
  "post_id": 1,
  "request_id": "...",
  "regeneration_type": "full",
  "prompt": null,
  "campaign": {
    "id": 1,
    "name": "..."
  },
  "client": {
    "id": 1,
    "name": "...",
    "logo_url": "...",
    "activity_description": "..."
  },
  "post": {
    "id": 1,
    "title": "...",
    "description": "...",
    "content_type": "...",
    "publishing_platforms": [],
    "media_count": 0,
    "primary_media_url": null,
    "primary_media_type": null,
    "media_items": [],
    "media": {}
  },
  "current_version": null,
  "callback_url": "..."
}
```

### Obbligatori per n8n
- `type`
- `post_id`
- `request_id`
- `regeneration_type` (valori ammessi: `full` | `caption` | `image`)
- `campaign.id`
- `campaign.name`
- `client.id`
- `client.name`
- `post.id`
- `post.title`
- `post.description`
- `post.content_type`
- `post.publishing_platforms`
- `callback_url`

### Opzionali / nullable
- `prompt`
- `client.logo_url`
- `client.activity_description`
- `current_version` (con campi: `id`, `version_number`, `title`, `caption`, `hashtags`, `image_url`, `image_urls`)
- `post.media_count`, `post.primary_media_url`, `post.primary_media_type`, `post.media_items`, `post.media`

## 4. n8n → Laravel: Callback nuova versione (Inbound)

- **Endpoint:** `POST /api/v1/integrations/n8n/marketing-campaign-posts/{post}/versions`
- **Controller:** `MarketingCampaignPostVersionController`
- **Request:** `StoreMarketingCampaignPostVersionRequest`

### Obbligatori
- `request_id`
- `regeneration_type`

In base al `regeneration_type`:
- **`full`**: richiede almeno `caption` e (`image_url` oppure `image_urls`)
- **`caption`**: richiede `caption` (immagine ereditata)
- **`image`**: richiede (`image_url` oppure `image_urls`) (testo ereditato)

### Opzionali
- `external_generation_id`, `title`, `hashtags`, `prompt_used`, `raw_payload`

### Alias accettati e preferenze
- **Caption:** preferenza `caption`, fallback `text`, `description`, `copy`
- **Immagine singola:** preferenza `image_url`, fallback `media_url`, `url`
- **Immagini multiple:** preferenza `image_urls`, fallback `images` (se stringa, diventa array)
- **Hashtag:** array `hashtags` oppure stringa csv
- **Payload annidati:** supportati wrap `{ "data": {} }` oppure `{ "body": {} }`

## 5. n8n → Laravel: Callback result legacy (Inbound)

- **Endpoint:** `POST /api/v1/integrations/n8n/marketing-campaign-posts/result`
- **Controller:** `MarketingCampaignPostResultController`
- **Request:** `StoreMarketingCampaignPostResultRequest`

Richiede `post_id`, `request_id`, `regeneration_type` e le stesse regole alias/condizionali delle versioni (`full`, `caption`, `image`).

## 6. n8n → Laravel: Failed callback (Inbound)

- **Endpoint:** `POST /api/v1/integrations/n8n/marketing-campaign-posts/{post}/failed`
- **Controller:** `MarketingCampaignPostFailedController`

- **Obbligatori:** `request_id`, `error`
- **Comportamento:** Se request_id non coincide, 400. Se coincide, 200 e il post torna al suo `n8n_previous_status` o `Generated` con salvataggio errore in `n8n_error` e timestamp in `n8n_completed_at`.

## 7. n8n → Laravel: Creazione ticket (Inbound)

- **Endpoint:** `POST /api/v1/integrations/n8n/tickets`
- **Controller:** `N8nTicketController`
- **Request:** `CreateTicketFromN8nRequest`

### Obbligatori
- Uno tra `client_id` e `project_id`
- `source` (ammessi: `whatsapp`, `n8n`, `email`, `manual`)
- `n8n_execution_id`

### Opzionali e Default
- `title` (default: "Ticket WhatsApp")
- `description` (default: `context.original_message` oppure "Ticket creato automaticamente da n8n.")
- `priority` (default: `medium`. Ammessi: `low`, `medium`, `high`, `urgent`)
- `context`

**Idempotenza:** Se esiste già un ticket con stessa `source` + `n8n_execution_id`, non crea doppione.

## 8. n8n → Laravel: Chatbot client message (Inbound)

- **Endpoint:** `POST /api/v1/integrations/n8n/chatbot/client-message`
- **Controller:** `N8nChatbotController@store`

### Obbligatori
- Uno tra `client_id` e `phone`
- `session_type` (ammessi: `marketing`, `ticket`)
- `session_id`
- `message`
- `type` (ammessi: `comment`, `approval`, `change_request`)

### Regole
- `client_id` e `phone` se entrambi passati devono coincidere (altrimenti 409).
- Se `phone` non risolve un client, 404.
- `session_id` non inventato (deve corrispondere a records in `ChatbotMarketingPost` o `ChatbotTicket` se session_type = ticket).

## 9. n8n → Laravel: Stato messaggio outbound (Inbound)

- **Endpoint:** `POST /api/v1/integrations/n8n/chatbot/outgoing-messages/{messageId}/status`

- **messageId:** formato `ticket_comment_{id}` o `task_comment_{id}` (se non supportato, 400).
- **Obbligatorio:** `status` (`sent` o `failed`).
- **Opzionali:** `external_message_id`, `error`.
- **Regole:** Il commento deve esistere (404) e avere `delivery_channel = sody` (400). Lo stato aggiornabile solo da `pending` o `processing`. Se già uguale, risponde idempotente (200 senza errori).
