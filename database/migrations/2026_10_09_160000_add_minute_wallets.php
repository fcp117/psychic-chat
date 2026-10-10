<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up(): void {
  Schema::table('chat_sessions',fn(Blueprint $t)=>$t->unsignedTinyInteger('billing_version')->default(1));
  Schema::table('credit_transactions',function(Blueprint $t){$t->string('unit_type')->default('credits');$t->bigInteger('minute_earning_units')->default(0);});
  Schema::table('credit_purchases',function(Blueprint $t){$t->string('unit_type')->default('credits');$t->string('purchase_kind')->default('package');$t->boolean('welcome')->default(false);});
  Schema::table('credit_packages',function(Blueprint $t){$t->boolean('welcome')->default(false);$t->string('unit_type')->default('credits');});
  Schema::table('users',fn(Blueprint $t)=>$t->boolean('welcome_eligible')->default(true));
  Schema::table('credit_shop_settings',function(Blueprint $t){$t->unsignedInteger('extra_minute_amount')->default(18767);$t->decimal('conversion_rate',10,4)->default(62.766);$t->date('conversion_date')->default('2026-10-08');});
  Schema::create('minute_lots',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->uuid('purchase_id')->nullable()->unique();$t->string('label');$t->bigInteger('original_units');$t->bigInteger('remaining_units');$t->timestamp('expires_at')->nullable()->index();$t->timestamps();});
  Schema::create('welcome_claims',function(Blueprint $t){$t->string('identity_key',64)->primary();$t->uuid('purchase_id');$t->timestamp('created_at');});
  DB::table('users')->where('credit_units','>',0)->orderBy('id')->chunkById(200,function($users){foreach($users as $u){DB::table('minute_lots')->insert(['user_id'=>$u->id,'label'=>'Converted existing balance (1 credit = 1 minute)','original_units'=>$u->credit_units,'remaining_units'=>$u->credit_units,'created_at'=>now(),'updated_at'=>now()]);DB::table('credit_transactions')->insert(['user_id'=>$u->id,'kind'=>'minute_conversion','amount_units'=>0,'balance_units'=>$u->credit_units,'earning_units'=>0,'minute_earning_units'=>0,'unit_type'=>'minutes','reason'=>'Existing credits converted 1:1 to minutes, fractions preserved; no expiry.','created_at'=>now(),'updated_at'=>now()]);}});
  DB::table('counselor_preferences')->update(['show_rate_notice'=>true,'accepted_rate'=>null]);
  DB::table('reading_invitations')->whereNull('confirmed_at')->update(['expires_at'=>now()]);
  DB::table('credit_packages')->update(['active'=>false]);
  foreach([['Welcome',5,555,true],['Quick Insight',10,2500,false],['Clarity',20,4500,false],['Blueprint',30,6000,false],['Deep Dive',60,11500,false]] as [$name,$minutes,$usd,$welcome])DB::table('credit_packages')->insert(['name'=>$name,'credits'=>$minutes,'amount'=>(int)round($usd*62.766),'active'=>true,'welcome'=>$welcome,'unit_type'=>'minutes','created_at'=>now(),'updated_at'=>now()]);
  DB::table('credit_shop_settings')->where('id',1)->update(['minimum_amount'=>100]);
 }
 public function down(): void { throw new RuntimeException('Financial conversion is forward-only. Restore a verified backup rather than dropping minute balances.'); }
};
