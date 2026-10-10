<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  Schema::create('moonoscope_settings',function(Blueprint $t){$t->id();$t->string('mode')->default('manual');$t->timestamps();});
  DB::table('moonoscope_settings')->insert(['id'=>1,'mode'=>'manual','created_at'=>now(),'updated_at'=>now()]);
  Schema::create('moonoscope_templates',function(Blueprint $t){$t->unsignedTinyInteger('house')->primary();$t->text('body')->nullable();$t->timestamps();});
  // Only verbatim illustrative examples supplied in section 7; no invented library.
  $examples=[5=>'A playful idea wants your attention today. Give yourself an hour to make something just for fun.',6=>'Tidy one small corner of your routine and notice how much lighter the afternoon feels.',11=>"Reach out to a friend you've missed. A short message can open a warm conversation."];
  foreach(range(1,12) as $h)DB::table('moonoscope_templates')->insert(['house'=>$h,'body'=>$examples[$h]??null,'created_at'=>now(),'updated_at'=>now()]);
  Schema::create('moonoscope_days',function(Blueprint $t){$t->id();$t->date('reading_date')->unique();$t->string('mode');$t->json('facts');$t->text('prompt');$t->json('lines');$t->json('published_lines')->nullable();$t->timestamp('published_at')->nullable();$t->unsignedInteger('version')->default(1);$t->foreignId('updated_by')->constrained('users');$t->timestamps();});
 }
 public function down(): void {Schema::dropIfExists('moonoscope_days');Schema::dropIfExists('moonoscope_templates');Schema::dropIfExists('moonoscope_settings');}
};
