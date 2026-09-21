<?php
require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';$app->instance('request',Illuminate\Http\Request::create('/'));$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();$app['env']='testing';
$db=tempnam(sys_get_temp_dir(),'demo-seeder-');config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'cache.default'=>'array','session.driver'=>'array']);Illuminate\Support\Facades\DB::purge('sqlite');
use App\Models\User;use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Hash;
class FixtureDemoSeeder extends Database\Seeders\DemoAccountsSeeder {protected function passwordFor(string $username):string{return 'Fixture123!';}}
function ck($v,$s){if(!$v)throw new RuntimeException($s);echo "PASS $s\n";}
try {
 Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
 (new FixtureDemoSeeder)->run();ck(User::count()===3,'three demo accounts created');
 $user=User::where('username','testuser')->firstOrFail();ck($user->credit_units===36000 && $user->available_credits==10,'ten initial credits');
 ck(User::whereNull('email_verified_at')->count()===0,'demo accounts verified');ck(User::where('role','counselor')->first()->is_approved,'counselor approved');
 ck(Hash::check('Fixture123!',$user->password),'password safely hashed');
 ck(DB::table('credit_transactions')->sum('amount_units')===36000,'initial credits recorded');
 $user->available_credits=3;$user->password='Changed123!';$user->save();$hash=$user->password;
 (new FixtureDemoSeeder)->run();$user->refresh();ck(User::count()===3 && $user->credit_units===10800 && $user->password===$hash && DB::table('credit_transactions')->count()===1,'rerun preserves existing accounts and balances');
 $user->role='counselor';$user->save();try{(new FixtureDemoSeeder)->run();throw new LogicException('conflict accepted');}catch(RuntimeException $e){ck(!($e instanceof LogicException),'role conflict refused');}
 $app['env']='production';try{(new FixtureDemoSeeder)->run();throw new LogicException('production accepted');}catch(RuntimeException $e){ck(!($e instanceof LogicException),'production blocked');}
 echo "ALL DEMO SEEDER CHECKS PASSED\n";
} finally {DB::disconnect('sqlite');@unlink($db);}
