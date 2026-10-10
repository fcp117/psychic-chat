<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
class Moonoscope {
 public const SIGNS=['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];
 public const THEMES=['self, energy and fresh starts','comfort, values and resources','conversations, learning and errands','home, family and rest','joy, creativity and play','routines, wellness and helpful tasks','partners and one-on-one connection','intimacy, shared support and renewal','big ideas, study and exploring','goals, work and reputation','friends, community and hopes','quiet reflection and inner rest'];
 public function fail(string $message): never {throw ValidationException::withMessages(['moonoscope'=>$message]);}
 public function ready(): bool {return filled(config('moonoscope.ai_key')) && filled(config('moonoscope.ai_model'));}
 public function calculate(string $date): array {
  $p=new Process([config('moonoscope.node_binary'),base_path('scripts/moonoscope.mjs'),$date],base_path());$p->setTimeout(20);
  try {$p->mustRun();$out=json_decode($p->getOutput(),true,512,JSON_THROW_ON_ERROR);if(($out['facts']['date']??null)!==$date)throw new \RuntimeException();return $out;}catch(\Throwable){$this->fail('Moon calculations are unavailable. Check the server Node path and Astronomy Engine installation.');}
 }
 public function validateLines(array $lines,bool $complete): array {
  if(count($lines)!==12)$this->fail('Provide all 12 signs in zodiac order.');$seen=[];
  foreach(self::SIGNS as $i=>$sign){$row=$lines[$i]??[];if(($row['sign']??null)!==$sign || !is_string($row['text']??null))$this->fail('Provide all 12 signs in zodiac order.');$text=trim($row['text']);
   if(mb_strlen($text)>1000 || count(preg_split('/\s+/u',$text,-1,PREG_SPLIT_NO_EMPTY))>35)$this->fail($sign.': use 35 words or fewer.');
   if($complete && ($text==='' || count(preg_split('/[.!?]+(?:\s|$)/u',$text,-1,PREG_SPLIT_NO_EMPTY))>2))$this->fail($sign.': provide one or two sentences.');
   if($complete && in_array(mb_strtolower($text),$seen,true))$this->fail('Each sign needs distinct wording.');
   $seen[]=mb_strtolower($text);$lines[$i]=['sign'=>$sign,'text'=>$text];
  }return $lines;
 }
 public function draft(string $mode,array $calculated): array {
  $lines=array_map(fn($s)=>['sign'=>$s,'text'=>''],self::SIGNS);
  if($mode==='templates'){$templates=DB::table('moonoscope_templates')->pluck('body','house');foreach($calculated['facts']['houses'] as $i=>$h)$lines[$i]['text']=$templates[$h['house']]??'';}
  if($mode==='ai'){
   if(!$this->ready())$this->fail('AI-assisted drafting needs a server API key and model. Manual drafting remains available.');
   try {$r=Http::connectTimeout(10)->timeout(45)->withHeaders(['x-api-key'=>config('moonoscope.ai_key'),'anthropic-version'=>'2023-06-01'])->post('https://api.anthropic.com/v1/messages',['model'=>config('moonoscope.ai_model'),'max_tokens'=>1800,'messages'=>[['role'=>'user','content'=>$calculated['prompt']]]]);
    if(!$r->successful())throw new \RuntimeException();$text=collect($r->json('content',[]))->where('type','text')->pluck('text')->implode('');$lines=json_decode($text,true,512,JSON_THROW_ON_ERROR);if(!is_array($lines))throw new \RuntimeException();
   }catch(\Throwable){$this->fail('AI drafting failed. No existing content was replaced. Try manual mode or check the provider configuration.');}
   return $this->validateLines($lines,true);
  }return $this->validateLines($lines,false);
 }
 public function audit(int $actor,string $action,string $target,$after): void {DB::table('admin_audits')->insert(['actor_id'=>$actor,'action'=>'moonoscope.'.$action,'target'=>$target,'before'=>null,'after'=>json_encode($after),'created_at'=>now(),'updated_at'=>now()]);}
}
