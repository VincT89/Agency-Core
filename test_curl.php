<?php
$ch = curl_init('http://127.0.0.1:8000/api/v1/integrations/n8n/marketing-campaign-posts/33/versions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'request_id' => 'cmp_293334bd-ac73-48e0-a0de-a81958e371c5',
    'regeneration_type' => 'full',
    'caption' => 'Questo è il testo pazzesco generato in dry-run',
    'image_url' => 'http://dummy.url'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer fake_token_for_e2e',
    'Accept: application/json',
    'Content-Type: application/json'
]);
$resp = curl_exec($ch);
echo "RESPONSE: $resp\n";
