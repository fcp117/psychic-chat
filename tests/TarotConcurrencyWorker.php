<?php
// Invoked only by TarotWorkflowCheck with its disposable SQLite database.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = $argv[1] ?? '';
if (!is_file($database) || !str_starts_with(basename($database), 'tarot-test-')) exit(2);
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.busy_timeout' => 10000, 'cache.default' => 'array', 'session.driver' => 'array']);
Illuminate\Support\Facades\DB::purge('sqlite');
try {
    $user = ($argv[2] ?? '0') !== '0' ? App\Models\User::findOrFail($argv[2]) : null;
    app(App\Services\DailyTarot::class)->draw($argv[3], $user, (string) Illuminate\Support\Str::uuid());
    echo 'drawn';
} catch (Illuminate\Validation\ValidationException $e) {
    echo 'limited';
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage());
    exit(2);
}
