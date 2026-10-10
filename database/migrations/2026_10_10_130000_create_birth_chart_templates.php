<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  Schema::create('birth_chart_templates',function(Blueprint $t){$t->id();$t->string('point');$t->string('sign');$t->text('body')->nullable();$t->unsignedInteger('version')->default(1);$t->timestamps();$t->unique(['point','sign']);});
  foreach(['Sun','Moon','Ascendant'] as $p)foreach(\App\Services\Moonoscope::SIGNS as $s)DB::table('birth_chart_templates')->insert(['point'=>$p,'sign'=>$s,'body'=>($p==='Sun'&&$s==='Cancer'?'Your Sun in Cancer shines with a nurturing glow.':($p==='Moon'&&$s==='Aries'?'Your Moon in Aries brings courage.':null)),'created_at'=>now(),'updated_at'=>now()]);
 }
 public function down(): void {Schema::dropIfExists('birth_chart_templates');}
};
