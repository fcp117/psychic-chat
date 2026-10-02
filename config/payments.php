<?php
// PayMongo sandbox only until merchant onboarding and end-to-end verification are complete.
return [
    'paymongo' => [
        'enabled' => env('PAYMONGO_ENABLED', false),
        // Explicit opt-in for a deployed demo. Sandbox credits use the site's balance.
        'allow_sandbox_in_production' => env('PAYMONGO_ALLOW_SANDBOX_IN_PRODUCTION', false),
        'secret' => env('PAYMONGO_TEST_SECRET'),
        'webhook_secret' => env('PAYMONGO_TEST_WEBHOOK_SECRET'),
        'minimum' => env('PAYMONGO_MINIMUM_CENTAVOS', 100),
        // List only methods activated for this merchant account in PayMongo.
        'payment_method_types' => array_filter(array_map('trim', explode(',', env('PAYMONGO_PAYMENT_METHOD_TYPES', 'card')))),
    ],
];
