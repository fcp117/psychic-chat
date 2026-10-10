<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  Schema::table('tarot_cards',function(Blueprint $t){$t->boolean('is_archived')->default(false);});
  // Retain original rows and images: immutable past readings still refer to them.
  DB::table('tarot_cards')->whereIn('slug',['the-fool','strength','the-star','the-sun','the-hermit'])->update(['is_active'=>false,'is_archived'=>true,'updated_at'=>now()]);
  foreach(['I','II','III','IV','V','VI','VII','VIII','IX','X','PAGE','KNIGHT','QUEEN','KING'] as $label){
   DB::table('tarot_cards')->insertOrIgnore(['slug'=>'violet-tides-'.strtolower($label),'name'=>ucfirst(strtolower($label)).' of Tides','category'=>'','keywords'=>'','meaning'=>'','guidance'=>'','reflection'=>'','image_path'=>'images/tarot/violet-tides/'.$label.'.png','is_active'=>false,'is_archived'=>false,'created_at'=>now(),'updated_at'=>now()]);
  }
  // Preserve the Roman numeral labels printed on the supplied artwork.
  foreach(['I','II','III','IV','V','VI','VII','VIII','IX','X'] as $label)DB::table('tarot_cards')->where('slug','violet-tides-'.strtolower($label))->update(['name'=>$label.' of Tides']);
 }
 public function down(): void {throw new RuntimeException('Restore the pre-import backup to undo the catalogue replacement without losing editorial work.');}
};
