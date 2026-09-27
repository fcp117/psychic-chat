<?php

// Safe local configuration check: no email, payment, or AI request is sent.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function stagingCheck(bool $value, string $label): void
{
    if (! $value) throw new RuntimeException($label);
    echo "PASS {$label}\n";
}

stagingCheck((bool) app('router')->getRoutes()->getByName('about'), 'About page route is registered');
stagingCheck((bool) app('router')->getRoutes()->getByName('assistant.respond'), 'virtual guide route is registered');
stagingCheck(config('database.connections.mysql.strict') === true, 'MySQL strict mode is enabled');
stagingCheck(config('database.connections.mysql.charset') === 'utf8mb4', 'MySQL uses utf8mb4');
stagingCheck(config('queue.default') === 'database', 'database queue is the configured default');
stagingCheck(config('broadcasting.default') === 'reverb', 'Reverb is the configured broadcaster');
stagingCheck(config('mail.mailers.resend.transport') === 'resend', 'Resend transport is available');

$originalEnvironment = $app['env'];
$app['env'] = 'production';
config(['payments.stripe.enabled' => true, 'payments.stripe.secret' => 'sk_test_fixture', 'payments.stripe.webhook_secret' => 'whsec_fixture']);
stagingCheck(! app(App\Services\PaymentGateway::class)->ready('stripe'), 'sandbox checkout is blocked in production');
$app['env'] = $originalEnvironment;

echo "STAGING READINESS CONFIGURATION CHECK PASSED\n";
