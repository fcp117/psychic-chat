<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->instance('request',Illuminate\Http\Request::create('/'));
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=tempnam(sys_get_temp_dir(),'admin-command-');
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'session.driver'=>'array','cache.default'=>'array']);
Illuminate\Support\Facades\DB::purge('sqlite');
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Tester\CommandTester;
function ck($v,$label){if(!$v)throw new RuntimeException($label);echo "PASS $label\n";}
function runAdmin($answers,$interactive=true){
 $command=new App\Console\Commands\CreateAdmin;$command->setLaravel(app());
 $tester=new CommandTester($command);$tester->setInputs($answers);
 $status=$tester->execute([],['interactive'=>$interactive]);return [$status,$tester->getDisplay()];
}
try {
 $app['env']='testing';Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
 $secret='Aa1!'.bin2hex(random_bytes(12));
 foreach(['staging','production'] as $environment){
  $app['env']=$environment;
  [$status,$output]=runAdmin(['Admin Name',strtoupper($environment),strtoupper($environment).'@EXAMPLE.TEST','1990-01-01',$secret,$secret,'yes']);
  $user=User::where('username',$environment)->first();
  ck($status===0 && $user?->role==='admin' && $user->hasVerifiedEmail(),$environment.' creates verified admin');
  ck(Hash::check($secret,$user->password) && !str_contains($output,$secret),'password hashed and absent from output');
  ck($user->credit_units===0 && $user->birthdate->format('Y-m-d')==='1990-01-01','required profile values and no extra credits');
 }
 $before=User::count();
 [$status]=runAdmin(['Cancel','cancel','cancel@example.test','1990-01-01',$secret,$secret,'no']);ck($status===0 && User::count()===$before,'declined confirmation creates nothing');
 [$status]=runAdmin(['Mismatch','mismatch','mismatch@example.test','1990-01-01',$secret,$secret.'x']);ck($status!==0 && User::count()===$before,'password mismatch rejected');
 [$status]=runAdmin(['Weak','weak','weak@example.test','1990-01-01','123','123']);ck($status!==0 && User::count()===$before,'existing password policy enforced');
 [$status]=runAdmin(['Minor','minor','minor@example.test',now('Asia/Manila')->subYears(17)->toDateString()]);ck($status!==0,'underage birthdate rejected');
 [$status]=runAdmin(['Invalid','invalid!','not-an-email','1990-01-01']);ck($status!==0,'invalid username and email rejected');
 $ordinary=User::forceCreate(['name'=>'Existing','username'=>'ordinary','email'=>'ordinary@example.test','password'=>$secret,'role'=>'user']);$old=$ordinary->fresh()->getAttributes();
 [$status]=runAdmin(['Other','ORDINARY','different@example.test','1990-01-01']);ck($status!==0 && $ordinary->fresh()->getAttributes()===$old,'existing username never promoted or changed');
 [$status]=runAdmin(['Other','different','ORDINARY@EXAMPLE.TEST','1990-01-01']);ck($status!==0 && $ordinary->fresh()->role==='user','existing email never promoted');
 [$status]=runAdmin([],false);ck($status!==0,'noninteractive execution refused');
 echo "ALL CREATE ADMIN COMMAND CHECKS PASSED\n";
} finally {DB::disconnect('sqlite');@unlink($db);}
