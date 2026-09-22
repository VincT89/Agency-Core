<?php

return [
    // Used only when no billing profile exists. Source: the supplied quote template.
    'issuer' => [
        'legal_name' => 'Sodano Consulting S.r.l.',
        'address' => 'Via Eduardo De Filippo, 4',
        'postal_code' => '70010',
        'city' => 'Valenzano',
        'province' => 'BA',
        'vat_number' => '08962440726',
        'tax_code' => '08962440726',
    ],
    'ai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_QUOTE_MODEL', 'gpt-4.1-mini'),
    ],
];
