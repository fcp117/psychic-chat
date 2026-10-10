<?php
// Standalone checks for installations without the optional PHPUnit dependency.
require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/Fixtures/TarotCardFixture.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = sys_get_temp_dir().'/tarot-test-'.bin2hex(random_bytes(8)).'.sqlite';
touch($database);
$uploadRoot = sys_get_temp_dir().'/tarot-upload-test-'.bin2hex(random_bytes(8));
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'cache.default' => 'array', 'session.driver' => 'array', 'broadcasting.default' => 'null', 'filesystems.disks.public.root' => $uploadRoot]);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\Storage::forgetDisk('public');
function tarotCheck($condition, $label) { if (!$condition) throw new RuntimeException($label); echo "PASS $label\n"; }
function tarotRejected($callback, $label) { try { $callback(); } catch (Illuminate\Validation\ValidationException $e) { tarotCheck(true, $label); return; } throw new RuntimeException($label); }
function tarotRequest($user, $data, $files = []) { $r = Illuminate\Http\Request::create('/admin/tarot-cards', 'POST', $data, [], $files); $r->setUserResolver(fn () => $user); return $r; }
$failed = false;
try {
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    (new Tests\Fixtures\TarotCardFixture)->run();
    $service = app(App\Services\DailyTarot::class);
    $uuid = fn () => (string) Illuminate\Support\Str::uuid();
    $guest = $service->guestKey($uuid());
    $createUser = fn ($role) => App\Models\User::forceCreate(['name' => 'Tarot Test', 'email' => $uuid().'@test.invalid', 'password' => 'unused', 'role' => $role, 'email_verified_at' => now()]);
    tarotCheck(App\Models\TarotCard::count() === 19, 'five test cards plus fourteen inactive drafts');
    foreach ([null, $createUser('user')] as $parallelUser) {
        $processes = [];
        $parallelGuest = $service->guestKey($uuid());
        for ($i = 0; $i < 6; $i++) {
            $process = proc_open([PHP_BINARY, __DIR__.'/TarotConcurrencyWorker.php', $database, (string) ($parallelUser?->id ?? 0), $parallelGuest], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) throw new RuntimeException('Could not start concurrency worker');
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }
        foreach ($processes as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            if (proc_close($process) !== 0) throw new RuntimeException('Concurrent draw error: '.$error.$output);
        }
        tarotCheck(count($service->state($parallelGuest, $parallelUser)['draws']) === ($parallelUser ? 3 : 1), 'simultaneous requests respect '.($parallelUser ? 'member' : 'guest').' allowance');
    }
    $beforeCount = Illuminate\Support\Facades\DB::table('tarot_draws')->count();
    $token = $uuid();
    $service->draw($guest, null, $token);
    $service->draw($guest, null, $token);
    tarotCheck(Illuminate\Support\Facades\DB::table('tarot_draws')->count() === $beforeCount + 1, 'retry is idempotent');
    tarotCheck($service->state($guest, null)['remaining'] === 0, 'guest allowance is one');
    tarotRejected(fn () => $service->draw($guest, null, $uuid()), 'second guest draw denied');
    $owner = $createUser('user');
    tarotCheck($service->state($guest, $owner)['remaining'] === 2, 'guest draw transferred to account');
    tarotCheck(count($service->state($guest, null)['draws']) === 0, 'claimed reading private after logout');
    $other = $createUser('user');
    tarotCheck(count($service->state($guest, $other)['draws']) === 0, 'another account cannot claim existing reading');
    foreach (['user', 'counselor', 'admin'] as $role) {
        $user = $createUser($role);
        for ($i = 0; $i < 3; $i++) $service->draw($service->guestKey($uuid()), $user, $uuid());
        tarotCheck($service->state($service->guestKey($uuid()), $user)['remaining'] === 0, "$role allowance shared across browsers");
        tarotCheck(Illuminate\Support\Facades\DB::table('tarot_draws')->where('user_id', $user->id)->distinct()->count('tarot_card_id') === 3, "$role avoids same-day repeats when possible");
        tarotRejected(fn () => $service->draw($service->guestKey($uuid()), $user, $uuid()), "$role fourth draw denied");
    }
    Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse('2026-10-08 23:59:59', 'Asia/Manila'));
    $midnightGuest = $service->guestKey($uuid());
    $service->draw($midnightGuest, null, $uuid());
    tarotCheck($service->state($midnightGuest, null)['remaining'] === 0, 'allowance spent before midnight');
    Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse('2026-10-09 00:00:00', 'Asia/Manila'));
    tarotCheck($service->state($midnightGuest, null)['remaining'] === 1, 'allowance resets at Philippine midnight');
    Illuminate\Support\Carbon::setTestNow();
    App\Models\TarotCard::query()->update(['is_active' => false]);
    $card = App\Models\TarotCard::where('slug','the-fool')->first();
    $card->update(['is_active' => true]);
    $snapshotGuest = $service->guestKey($uuid());
    $service->draw($snapshotGuest, null, $uuid());
    $snapshot = $service->state($snapshotGuest, null)['draws'][0]['reading'];
    tarotCheck($snapshot['name'] === $card->name, 'inactive cards excluded');
    $card->update(['name' => 'Edited name', 'image_path' => 'tarot/changed.png', 'is_active' => false]);
    tarotCheck($snapshot === $service->state($snapshotGuest, null)['draws'][0]['reading'], 'saved text and image unchanged after edit');
    tarotRejected(fn () => $service->draw($service->guestKey($uuid()), null, $uuid()), 'empty active deck handled without spending draw');
    (new Tests\Fixtures\TarotCardFixture)->run();
    tarotCheck($card->fresh()->name === 'Edited name' && !$card->fresh()->is_active && App\Models\TarotCard::count() === 19, 'repeat seed preserves admin edits');

    $admin = $createUser('admin');
    $controller = app(App\Http\Controllers\AdminTarotController::class);
    $data = ['name' => 'Uploaded card', 'category' => 'Major Arcana', 'keywords' => 'Reflection', 'meaning' => 'A meaning', 'guidance' => 'A small step', 'reflection' => 'A question?', 'is_active' => true];
    $image = fn () => new Illuminate\Http\UploadedFile(public_path('images/tarot/violet-tides/I.png'), 'card.png', 'image/png', null, true);
    $controller->store(tarotRequest($admin, $data, ['image' => $image()]));
    $uploaded = App\Models\TarotCard::latest('id')->first();
    $oldImage = $uploaded->image_path;
    tarotCheck(Illuminate\Support\Facades\Storage::disk('public')->exists($oldImage), 'admin image upload saved');
    $data['is_active'] = false;
    $controller->update(tarotRequest($admin, $data, ['image' => $image()]), $uploaded);
    tarotCheck(!$uploaded->fresh()->is_active && $oldImage !== $uploaded->fresh()->image_path, 'admin can replace and deactivate');
    tarotCheck(Illuminate\Support\Facades\Storage::disk('public')->exists($oldImage), 'old artwork retained for snapshots');
    tarotCheck(Illuminate\Support\Facades\DB::table('admin_audits')->where('action', 'tarot.save')->count() === 2, 'admin edits audited');
    tarotRejected(fn () => $controller->store(tarotRequest($admin, $data)), 'new card requires image');
    $badImage = new Illuminate\Http\UploadedFile(__FILE__, 'bad.svg', 'image/svg+xml', null, true);
    tarotRejected(fn () => $controller->update(tarotRequest($admin, $data, ['image' => $badImage]), $uploaded), 'unsafe upload rejected');
    foreach (['user', 'counselor'] as $role) {
        try { app(App\Http\Middleware\RequireRole::class)->handle(tarotRequest($createUser($role), []), fn () => true, 'admin'); throw new RuntimeException('Unauthorized admin access'); }
        catch (Symfony\Component\HttpKernel\Exception\HttpException $e) { tarotCheck($e->getStatusCode() === 403, "$role admin access denied"); }
    }
    foreach (['admin.tarot', 'admin.tarot.store', 'admin.tarot.update'] as $name) {
        $middleware = app('router')->getRoutes()->getByName($name)->gatherMiddleware();
        tarotCheck(in_array('auth', $middleware) && in_array('verified', $middleware) && in_array('role:admin', $middleware), "$name guarded");
    }
    $publicController = app(App\Http\Controllers\TarotController::class);
    tarotRejected(fn () => $publicController->draw(tarotRequest(null, ['request_token' => $uuid()]), $service), 'missing guest cookie rejected');
    echo "TAROT WORKFLOW CHECKS PASSED\n";
} catch (Throwable $e) {
    $failed = true;
    fwrite(STDERR, $e->getMessage()."\n".$e->getTraceAsString()."\n");
} finally {
    Illuminate\Support\Carbon::setTestNow();
    Illuminate\Support\Facades\DB::disconnect('sqlite');
    unlink($database);
    if (is_dir($uploadRoot)) (new Illuminate\Filesystem\Filesystem)->deleteDirectory($uploadRoot);
}
exit($failed ? 1 : 0);
