<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,Artisan,Hash};
use App\Models\User;
class FixtureRoleSeeder extends Database\Seeders\RoleTestAccountsSeeder {
 public int $prompts=0;
 protected function confirmSetup():bool{return true;}
 protected function passwordFor(string $username):string{$this->prompts++;return 'Only-a-test-password-927!';}
}
function qaCheck($value,$name){if(!$value)throw new RuntimeException($name);echo "PASS $name\n";}
$db=tempnam(sys_get_temp_dir(),'role-seeder-');$original=$app->environment();$failed=false;
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'cache.default'=>'array','session.driver'=>'array','broadcasting.default'=>'null']);DB::purge('sqlite');
try{
 Artisan::call('migrate',['--force'=>true]);
 $app->instance('env','staging');$seed=new FixtureRoleSeeder;$seed->run();
 qaCheck(User::count()===6&&$seed->prompts===6,'six roles created with individual password prompts');
 $coach=User::where('username','qa_coach')->first();$manager=User::where('username','qa_manager')->first();$client=User::where('username','qa_user')->first();
 qaCheck($coach->is_approved&&(int)$coach->manager_id===$manager->id,'coach approved and assigned to test manager');
 qaCheck($client->credit_units===108000&&DB::table('credit_transactions')->count()===1,'30 test minutes audited once');
 qaCheck(Hash::check('Only-a-test-password-927!',$client->password)&&$client->email_verified_at,'test login pre-verified with hashed password');
 $client->forceFill(['is_suspended'=>true])->save();$hash=$client->password;$seed->run();
 qaCheck(User::count()===6&&$seed->prompts===6&&$client->fresh()->is_suspended&&$client->fresh()->password===$hash,'rerun preserves existing accounts without password prompts');
 qaCheck(DB::table('credit_transactions')->count()===1,'rerun does not top up balance');
 $client->forceFill(['role'=>'support'])->save();
 try{$seed->run();throw new RuntimeException('Conflict accepted');}catch(RuntimeException $e){qaCheck(str_contains($e->getMessage(),'Identity conflict'),'conflicting role rejected without modification');}
 $app->instance('env','production');
 try{$seed->run();throw new RuntimeException('Production accepted');}catch(RuntimeException $e){qaCheck(str_contains($e->getMessage(),'blocked in production'),'production blocked');}
 echo "ALL SIX-ROLE SEEDER CHECKS PASSED\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,$e->getMessage()."\n");}
finally{$app->instance('env',$original);DB::disconnect('sqlite');if(is_file($db))unlink($db);}
exit($failed?1:0);
