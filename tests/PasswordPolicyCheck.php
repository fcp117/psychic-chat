<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
$cases=[
    ['@Abcd123',true,'requested user password'],
    ['@Admin123',true,'requested admin password'],
    ['Abcd12!',false,'minimum length'],
    ['Abc123!@',false,'four letters'],
    ['abcd123!',false,'uppercase required'],
    ['ABCD123!',false,'lowercase required'],
    ['Abcdefg!',false,'number required'],
    ['Abcdef12',false,'special character required'],
    ['Abcd123 ',false,'space is not a special character'],
    ['Äbcd123!',true,'Unicode letters'],
    ['LongerPass123#',true,'longer passwords accepted'],
];
foreach($cases as [$value,$expected,$label]) {
    $v=Validator::make(['password'=>$value,'password_confirmation'=>$value],['password'=>['required','confirmed',Password::defaults()]]);
    if($v->passes()!==$expected)throw new RuntimeException('Failed: '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
$v=Validator::make(['password'=>'Abcd123!','password_confirmation'=>'different'],['password'=>['required','confirmed',Password::defaults()]]);
if($v->passes())throw new RuntimeException('Confirmation mismatch accepted');
echo "PASS confirmation must match\n";
foreach(['RegisteredUserController','NewPasswordController','PasswordController'] as $controller) {
    $source=file_get_contents(dirname(__DIR__).'/app/Http/Controllers/Auth/'.$controller.'.php');
    if(!str_contains($source,'Password::defaults()'))throw new RuntimeException('Policy bypass: '.$controller);
}
echo "PASS registration, reset and profile password changes share the policy\n";
