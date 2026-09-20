<?php
// Sandbox only until merchant onboarding and end-to-end verification are complete.
return [
    'gcash'=>['enabled'=>env('GCASH_ENABLED',false),'secret'=>env('PAYMONGO_TEST_SECRET'),'webhook_secret'=>env('PAYMONGO_TEST_WEBHOOK_SECRET'),'minimum'=>env('GCASH_MINIMUM_CENTAVOS',100)],
    'stripe'=>['enabled'=>env('STRIPE_ENABLED',false),'secret'=>env('STRIPE_TEST_SECRET'),'webhook_secret'=>env('STRIPE_TEST_WEBHOOK_SECRET'),'minimum'=>env('STRIPE_MINIMUM_CENTAVOS',100)],
    'paypal'=>['enabled'=>env('PAYPAL_ENABLED',false),'client_id'=>env('PAYPAL_SANDBOX_CLIENT_ID'),'secret'=>env('PAYPAL_SANDBOX_SECRET'),'webhook_id'=>env('PAYPAL_SANDBOX_WEBHOOK_ID'),'minimum'=>env('PAYPAL_MINIMUM_CENTAVOS',100)],
    'maya'=>['enabled'=>env('MAYA_ENABLED',false),'public'=>env('MAYA_SANDBOX_PUBLIC_KEY'),'secret'=>env('MAYA_SANDBOX_SECRET_KEY'),'minimum'=>env('MAYA_MINIMUM_CENTAVOS',100), 'webhook_ips'=>['13.229.160.234','3.1.199.75']],
];
