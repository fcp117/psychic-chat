<?php

namespace Tests\Feature;

use App\Models\{ChatSession, Message, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Notification, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function conversation(): array
    {
        $client = User::factory()->create(['role'=>'user']);
        $coach = User::factory()->create(['role'=>'counselor', 'is_approved'=>true]);
        $session = ChatSession::create(['client_id'=>$client->id, 'counselor_id'=>$coach->id, 'status'=>'completed']);
        return [$client, $coach, $session];
    }

    public function test_recovery_does_not_disclose_accounts_or_cooldown(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $responses = [];
        foreach ([$user->email, 'missing@example.invalid', $user->email] as $email) {
            $response = $this->from('/forgot-password')->post('/forgot-password', ['email'=>$email]);
            $response->assertRedirect('/forgot-password')->assertSessionHasNoErrors();
            $responses[] = session('status');
        }
        $this->assertCount(1, array_unique($responses));
        for ($i = 0; $i < 2; $i++) $this->postJson('/forgot-password', ['email'=>'missing@example.invalid'])->assertRedirect();
        $this->postJson('/forgot-password', ['email'=>'another@example.invalid'])->assertStatus(429);
    }

    public function test_chat_rate_limit_and_member_only_bounded_history(): void
    {
        [$client, $coach, $session] = $this->conversation();
        for ($i = 0; $i < 55; $i++) Message::create(['chat_session_id'=>$session->id, 'sender_id'=>$client->id, 'content'=>'History '.$i]);
        $this->actingAs($client)->getJson('/chat/'.$session->id.'/messages?before=999999')
            ->assertOk()->assertJsonCount(50, 'messages')->assertJsonPath('has_more', true);
        $first = Message::orderBy('id')->skip(5)->first()->id;
        $this->getJson('/chat/'.$session->id.'/messages?before='.$first)->assertOk()->assertJsonCount(5, 'messages')->assertJsonPath('has_more', false);
        $this->actingAs(User::factory()->create())->getJson('/chat/'.$session->id.'/messages?before=999999')->assertForbidden();
        $this->actingAs($client);
        // Invalid requests still consume the route quota without storing messages.
        for ($i = 0; $i < 30; $i++) $this->postJson('/chat/'.$session->id.'/message', [])->assertUnprocessable();
        $this->postJson('/chat/'.$session->id.'/message', [])->assertStatus(429);
    }

    public function test_upload_quota_covers_both_roles_and_keeps_text_available(): void
    {
        Storage::fake('local');
        [$client, $coach, $session] = $this->conversation();
        foreach ([$client, $coach] as $sender) {
            config(['chat.daily_upload_bytes'=>100, 'chat.stored_upload_bytes'=>100]);
            Message::create(['chat_session_id'=>$session->id, 'sender_id'=>$sender->id, 'content'=>'', 'attachment_path'=>'existing.pdf', 'attachment_bytes'=>100]);
            $this->actingAs($sender)->postJson('/chat/'.$session->id.'/message', [
                'request_key'=>(string) Str::uuid(),
                'attachment'=>UploadedFile::fake()->createWithContent('sample.pdf', "%PDF-1.4\n%%EOF"),
            ])->assertUnprocessable()->assertJsonValidationErrors('attachment');
            $this->postJson('/chat/'.$session->id.'/message', ['request_key'=>(string) Str::uuid(), 'content'=>'Text still works'])->assertOk();
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_development_seeders_fail_before_writing_in_production(): void
    {
        $this->app['env'] = 'production';
        foreach ([\Database\Seeders\DatabaseSeeder::class, \Database\Seeders\UserSeeder::class, \Database\Seeders\ChatSeeder::class, \Database\Seeders\DemoAccountsSeeder::class] as $class) {
            try { app($class)->run(); $this->fail('Production seeding was allowed'); }
            catch (\RuntimeException $error) { $this->assertNotEmpty($error->getMessage()); }
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_real_webhook_route_requires_signature_but_not_csrf(): void
    {
        // Unlike normal Laravel tests, keep CSRF middleware active here.
        $this->app['env'] = 'local';
        config(['payments.paymongo.enabled'=>true, 'payments.paymongo.secret'=>'sk_test_fixture',
            'payments.paymongo.webhook_secret'=>'test-signing-secret', 'payments.paymongo.payment_method_types'=>['card']]);
        $body = json_encode(['data'=>['attributes'=>['type'=>'checkout_session.payment.paid', 'livemode'=>false, 'data'=>['id'=>'cs_fixture']]]]);
        $time = time();
        $signature = 't='.$time.',te='.hash_hmac('sha256', $time.'.'.$body, 'test-signing-secret');
        $this->call('POST', '/payment-webhooks/paymongo', [], [], [], ['HTTP_ACCEPT'=>'application/json', 'CONTENT_TYPE'=>'application/json', 'HTTP_PAYMONGO_SIGNATURE'=>$signature], $body)->assertOk()->assertJson(['received'=>true]);
        $this->call('POST', '/payment-webhooks/paymongo', [], [], [], ['HTTP_ACCEPT'=>'application/json', 'CONTENT_TYPE'=>'application/json'], $body)->assertStatus(400);
        $this->postJson('/forgot-password', ['email'=>'missing@example.invalid'])->assertStatus(419);
    }
}
