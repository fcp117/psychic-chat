<?php

namespace Tests\Feature;

use App\Models\TarotCard;
use App\Models\User;
use App\Services\DailyTarot;
require_once dirname(__DIR__).'/Fixtures/TarotCardFixture.php';
use Tests\Fixtures\TarotCardFixture as TarotCardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DailyTarotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(TarotCardSeeder::class);
        $this->travelTo(now('Asia/Manila')->setDate(2026, 10, 8)->setTime(12, 0));
    }

    private function member(string $role = 'user'): User
    {
        return User::factory()->create(['role' => $role, 'is_suspended' => false, 'email_verified_at' => now()]);
    }

    private function token(): string { return (string) Str::uuid(); }

    public function test_guest_page_sets_private_cookie_and_one_draw_allowance(): void
    {
        $this->get(route('tarot.index'))->assertOk()->assertCookie(DailyTarot::COOKIE)
            ->assertInertia(fn (Assert $page) => $page->component('Tarot/Index')->where('tarot.limit', 1)->where('tarot.remaining', 1)->where('tarot.deck_size', 5));
    }

    public function test_guest_limit_and_idempotent_retry(): void
    {
        $service = app(DailyTarot::class);
        $guest = $service->guestKey($this->token());
        $token = $this->token();
        $service->draw($guest, null, $token);
        $service->draw($guest, null, $token);
        $this->assertSame(1, DB::table('tarot_draws')->count());
        $this->assertSame(0, $service->state($guest, null)['remaining']);
        $this->expectException(ValidationException::class);
        $service->draw($guest, null, $this->token());
    }

    public function test_http_draw_requires_cookie_and_valid_token(): void
    {
        $this->post(route('tarot.draw'), ['request_token' => $this->token()])->assertSessionHasErrors('draw');
        $this->withCookie(DailyTarot::COOKIE, $this->token())->post(route('tarot.draw'), ['request_token' => 'bad'])->assertSessionHasErrors('request_token');
        $this->assertSame(0, DB::table('tarot_draws')->count());
    }

    public function test_guest_can_draw_through_http_then_reopen_without_spending(): void
    {
        $this->withCookie(DailyTarot::COOKIE, $this->token());
        $this->post(route('tarot.draw'), ['request_token' => $this->token()])->assertRedirect(route('tarot.index'));
        $this->get(route('tarot.index'))->assertInertia(fn (Assert $page) => $page->has('tarot.draws', 1)->where('tarot.remaining', 0));
        $this->post(route('tarot.draw'), ['request_token' => $this->token()])->assertSessionHasErrors('draw');
        $this->assertSame(1, DB::table('tarot_draws')->count());
    }

    public function test_members_of_each_role_have_three_draws_across_browsers(): void
    {
        $service = app(DailyTarot::class);
        foreach (['user', 'counselor', 'admin'] as $role) {
            $user = $this->member($role);
            for ($i = 0; $i < 3; $i++) $service->draw($service->guestKey($this->token()), $user, $this->token());
            $this->assertSame(0, $service->state($service->guestKey($this->token()), $user)['remaining']);
            $this->assertSame(3, DB::table('tarot_draws')->where('user_id', $user->id)->distinct()->count('tarot_card_id'));
            try { $service->draw($service->guestKey($this->token()), $user, $this->token()); $this->fail('Fourth draw accepted'); }
            catch (ValidationException $exception) { $this->assertArrayHasKey('draw', $exception->errors()); }
        }
    }

    public function test_guest_draw_is_claimed_once_and_private_after_logout(): void
    {
        $service = app(DailyTarot::class);
        $guest = $service->guestKey($this->token());
        $service->draw($guest, null, $this->token());
        $owner = $this->member();
        $this->assertSame(2, $service->state($guest, $owner)['remaining']);
        $this->assertCount(0, $service->state($guest, null)['draws']);
        $other = $this->member();
        $this->assertCount(0, $service->state($guest, $other)['draws']);
        $this->assertSame(3, $service->state($guest, $other)['remaining']);
        $this->assertSame($owner->id, DB::table('tarot_draws')->value('user_id'));
    }

    public function test_philippine_midnight_resets_allowance_but_keeps_history(): void
    {
        $service = app(DailyTarot::class);
        $guest = $service->guestKey($this->token());
        $this->travelTo(now('Asia/Manila')->setTime(23, 59, 59));
        $service->draw($guest, null, $this->token());
        $this->assertSame(0, $service->state($guest, null)['remaining']);
        $this->travel(1)->seconds();
        $this->assertSame(1, $service->state($guest, null)['remaining']);
        $service->draw($guest, null, $this->token());
        $this->assertSame(2, DB::table('tarot_draws')->count());
    }

    public function test_inactive_cards_excluded_and_saved_content_unchanged(): void
    {
        TarotCard::query()->update(['is_active' => false]);
        $card = TarotCard::where('slug','the-fool')->first();
        $card->update(['is_active' => true]);
        $service = app(DailyTarot::class);
        $guest = $service->guestKey($this->token());
        $service->draw($guest, null, $this->token());
        $original = $service->state($guest, null)['draws'][0]['reading'];
        $this->assertSame($card->name, $original['name']);
        $card->update(['name' => 'Changed', 'meaning' => 'Changed meaning', 'image_path' => 'tarot/new.png', 'is_active' => false]);
        $this->assertSame($original, $service->state($guest, null)['draws'][0]['reading']);
        $this->expectException(ValidationException::class);
        $service->draw($service->guestKey($this->token()), null, $this->token());
    }

    public function test_admin_routes_are_restricted_and_suspension_is_respected(): void
    {
        $this->get(route('admin.tarot'))->assertRedirect(route('login'));
        foreach (['user', 'counselor'] as $role) {
            $this->actingAs($this->member($role))->get(route('admin.tarot'))->assertForbidden();
            $this->post(route('admin.tarot.store'), [])->assertForbidden();
            $this->post(route('admin.tarot.update', TarotCard::where('slug','the-fool')->first()->id), [])->assertForbidden();
        }
        $admin = $this->member('admin');
        $this->actingAs($admin)->get(route('admin.tarot'))->assertOk();
        $admin->update(['is_suspended' => true]);
        $this->get(route('tarot.index'))->assertForbidden();
        $this->post(route('tarot.draw'), ['request_token' => $this->token()])->assertForbidden();
    }

    public function test_admin_can_upload_edit_and_deactivate_without_overwriting_images(): void
    {
        Storage::fake('public');
        $this->actingAs($this->member('admin'));
        $data = ['name' => 'A new card', 'category' => 'Major Arcana', 'keywords' => 'Reflection', 'meaning' => 'A meaning', 'guidance' => 'A small step', 'reflection' => 'A question?', 'is_active' => true];
        $image = UploadedFile::fake()->image('card.jpg', 400, 600);
        $this->post(route('admin.tarot.store'), $data + ['image' => $image])->assertSessionHasNoErrors()->assertRedirect(route('admin.tarot'));
        $card = TarotCard::latest('id')->first();
        $oldImage = $card->image_path;
        Storage::disk('public')->assertExists($oldImage);
        $data['is_active'] = false;
        $this->post(route('admin.tarot.update', $card->id), $data + ['image' => UploadedFile::fake()->image('replacement.png', 400, 600)])->assertSessionHasNoErrors();
        $this->assertFalse($card->fresh()->is_active);
        $this->assertNotSame($oldImage, $card->fresh()->image_path);
        Storage::disk('public')->assertExists($oldImage);
        $this->assertSame(2, DB::table('admin_audits')->where('action', 'tarot.save')->count());
    }

    public function test_admin_upload_validation_and_repeat_seed_preserve_edits(): void
    {
        $this->actingAs($this->member('admin'));
        $card = TarotCard::where('slug','the-fool')->first();
        $data = $card->only(['name', 'category', 'keywords', 'meaning', 'guidance', 'reflection', 'is_active']);
        $this->post(route('admin.tarot.update', $card->id), $data + ['image' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')])->assertSessionHasErrors('image');
        $this->post(route('admin.tarot.store'), $data)->assertSessionHasErrors('image');
        $card->update(['name' => 'My edited name', 'is_active' => false]);
        $this->seed(TarotCardSeeder::class);
        $this->assertSame(19, TarotCard::count());
        $this->assertSame('My edited name', $card->fresh()->name);
        $this->assertFalse($card->fresh()->is_active);
    }
}
