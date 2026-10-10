<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=tempnam(sys_get_temp_dir(),'conversation-');$files=sys_get_temp_dir().'/conversation-files-'.bin2hex(random_bytes(6));
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$db,'cache.default'=>'array','session.driver'=>'array','broadcasting.default'=>'null','filesystems.disks.local.root'=>$files]);
Illuminate\Support\Facades\DB::purge('sqlite');
function ct($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function cr($user,$data=[],$method='POST'){$r=Illuminate\Http\Request::create('/',$method,$data);$r->setUserResolver(fn()=>$user);$r->headers->set('X-Inertia','true');return $r;}
function denied($fn,$code,$label){try{$fn();throw new RuntimeException('Unexpected success: '.$label);}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){ct($e->getStatusCode()===$code,$label);}}
$failed=false;
try {
 Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
 $make=fn($name,$role)=>App\Models\User::forceCreate(['name'=>$name,'email'=>$name.'@example.invalid','password'=>'unused','role'=>$role,'is_approved'=>true,'is_suspended'=>false,'email_verified_at'=>now(),'credit_units'=>36000]);
 $client=$make('Client','user');$coach=$make('Coach','counselor');$other=$make('Other','user');$admin=$make('Admin','admin');
 $tools=app(App\Http\Controllers\ConversationToolsController::class);$chat=app(App\Http\Controllers\ChatController::class);$support=app(App\Http\Controllers\SupportInboxController::class);
 $tools->open(cr($client),$coach);$s=App\Models\ChatSession::first();$tools->open(cr($client),$coach);ct(App\Models\ChatSession::count()===1,'opening free chat is idempotent');
 $data=['content'=>'Offline hello','request_key'=>(string)Illuminate\Support\Str::uuid()];
 $message=$chat->store(cr($client,$data),$s)->getData(true)['message'];$chat->store(cr($client,$data),$s);
 ct(App\Models\Message::count()===1,'message retries do not duplicate');ct($client->fresh()->credit_units===36000 && $s->fresh()->status==='rejected','offline message does not bill or start reading');
 denied(fn()=>$chat->store(cr($other,$data),$s),403,'outsider cannot send');
 $tools->notes(cr($coach,['body'=>'Secret coach notes'],'PUT'),$s);
 denied(fn()=>$tools->notes(cr($client,[],'GET'),$s),403,'client cannot read notes');denied(fn()=>$tools->notes(cr($admin,[],'GET'),$s),403,'admin cannot read private coach notes');
 $text=$tools->transcript(cr($client,[],'GET'),$s)->getData(true)['text'];ct(str_contains($text,'Offline hello')&&!str_contains($text,'Secret coach notes'),'transcript includes messages but excludes notes');
 denied(fn()=>$tools->transcript(cr($other,[],'GET'),$s),403,'outsider cannot export');denied(fn()=>$tools->transcript(cr($admin,[],'GET'),$s),403,'admin cannot export arbitrary chats');
 $file=Illuminate\Http\UploadedFile::fake()->createWithContent('sample.pdf',"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
 $r=cr($client,['request_key'=>(string)Illuminate\Support\Str::uuid(),'content'=>'']);$r->files->set('attachment',$file);
 $uploaded=$chat->store($r,$s)->getData(true)['message'];ct(!array_key_exists('attachment_path',$uploaded)&&$uploaded['attachment_name']==='sample.pdf','private upload hides storage path');
 $attachment=App\Models\Message::findOrFail($uploaded['id']);$tools->attachment(cr($coach),$attachment);denied(fn()=>$tools->attachment(cr($other),$attachment),403,'attachment download requires membership');
 $bad=cr($client,['request_key'=>(string)Illuminate\Support\Str::uuid(),'content'=>'']);$bad->files->set('attachment',Illuminate\Http\UploadedFile::fake()->createWithContent('bad.html','<script>alert(1)</script>'));
 try{$chat->store($bad,$s);throw new RuntimeException('HTML upload accepted');}catch(Illuminate\Validation\ValidationException $e){ct(true,'HTML upload rejected');}
 $tools->invite(cr($coach),$s);$inv=Illuminate\Support\Facades\DB::table('reading_invitations')->first();ct($client->fresh()->credit_units===36000 && App\Models\ChatSession::count()===1,'invitation alone starts no reading or billing');
 denied(fn()=>$tools->confirm(cr($other,['consent'=>true]),$inv->id),403,'only client may confirm invitation');
 $tools->confirm(cr($client,['consent'=>true]),$inv->id);$paid=App\Models\ChatSession::latest('id')->first();ct($paid->status==='pending' && $client->fresh()->credit_units===36000,'client confirmation waits for coach acceptance without billing');
 try{$tools->confirm(cr($client,['consent'=>true]),$inv->id);throw new RuntimeException('Duplicate confirmation accepted');}catch(Illuminate\Validation\ValidationException $e){ct(true,'invitation cannot be confirmed twice');}
 $chat->accept(cr($coach),$paid);ct($paid->fresh()->status==='active','coach acceptance starts confirmed reading');
 $heartbeat=$chat->heartbeat(cr($coach),$s)->getData(true);ct($heartbeat['session']['timing']['remaining']===600,'coach timer uses client balance and agreed rate');
 $chat->end(cr($client,['reason'=>'finished']),$paid);$chat->store(cr($coach,['content'=>'Free follow-up','request_key'=>(string)Illuminate\Support\Str::uuid()]),$paid->fresh());ct($paid->fresh()->status==='completed','free replies after reading do not restart billing');
 $sd=['category'=>'technical','body'=>'Help please','request_key'=>(string)Illuminate\Support\Str::uuid()];$support->send(cr($client,$sd));$support->send(cr($client,$sd));ct(Illuminate\Support\Facades\DB::table('support_messages')->count()===1,'support retries do not duplicate');
 $thread=Illuminate\Support\Facades\DB::table('support_threads')->first();denied(fn()=>$support->index(cr($other,['thread'=>$thread->id],'GET')),403,'support conversations are private');
 $support->send(cr($admin,['thread'=>$thread->id,'category'=>'technical','body'=>'We can help','request_key'=>(string)Illuminate\Support\Str::uuid()]));
 $support->index(cr($client,['thread'=>$thread->id],'GET'));ct(Illuminate\Support\Facades\DB::table('support_messages')->where('sender_id',$admin->id)->whereNotNull('read_at')->exists(),'support replies mark read for recipient');
 $support->send(cr($client,['category'=>'credits','body'=>'Credit question','request_key'=>(string)Illuminate\Support\Str::uuid()]));ct(Illuminate\Support\Facades\DB::table('support_threads')->count()===2,'technical and credit support are separate');
 echo "CONVERSATION TOOLS CHECKS PASSED\n";
}catch(Throwable $e){$failed=true;fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString());}
finally{Illuminate\Support\Facades\DB::disconnect('sqlite');unlink($db);Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('chat-attachments');if(is_dir($files))rmdir($files);}
exit($failed?1:0);
