<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app['env']='testing';
config(['mail.default'=>'resend','services.resend.key'=>'re_test_placeholder_not_a_real_key']);
$transport=app('mail.manager')->mailer()->getSymfonyTransport();
if (!$transport instanceof Illuminate\Mail\Transport\ResendTransport) throw new RuntimeException('Resend transport did not resolve');
if (config('mail.mailers.resend.transport')!=='resend') throw new RuntimeException('Wrong mail transport');
echo "PASS Laravel resolves official Resend transport using configured SDK\n";
echo "No email sent or external API called.\n";