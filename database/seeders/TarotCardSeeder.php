<?php
namespace Database\Seeders;
use App\Models\TarotCard;
use Illuminate\Database\Seeder;
class TarotCardSeeder extends Seeder {
 public function run(): void {
  foreach (['I','II','III','IV','V','VI','VII','VIII','IX','X','PAGE','KNIGHT','QUEEN','KING'] as $label) {
   $name = strlen($label)>3 ? ucfirst(strtolower($label)) : $label;
   TarotCard::firstOrCreate(['slug'=>'violet-tides-'.strtolower($label)],['name'=>$name.' of Tides','category'=>'','keywords'=>'','meaning'=>'','guidance'=>'','reflection'=>'','image_path'=>'images/tarot/violet-tides/'.$label.'.png','is_active'=>false,'is_archived'=>false]);
  }
 }
}
