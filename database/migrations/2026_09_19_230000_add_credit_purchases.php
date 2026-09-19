<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        Schema::create('credit_shop_settings',function(Blueprint $t) {
            $t->id(); $t->boolean('custom_enabled')->default(true); $t->unsignedInteger('price_per_credit')->default(100);
            $t->unsignedInteger('minimum_amount')->default(100); $t->timestamps();
        });
        DB::table('credit_shop_settings')->insert(['id'=>1,'custom_enabled'=>true,'price_per_credit'=>100,'minimum_amount'=>100,'created_at'=>now(),'updated_at'=>now()]);
        Schema::create('credit_packages',function(Blueprint $t) {
            $t->id(); $t->string('name',100); $t->unsignedInteger('credits'); $t->unsignedInteger('amount'); $t->boolean('active')->default(true); $t->timestamps();
        });
        DB::table('credit_packages')->insert(['name'=>'1 Credit','credits'=>1,'amount'=>100,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        Schema::create('credit_purchases',function(Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('package_id')->nullable()->constrained('credit_packages')->nullOnDelete();
            $t->uuid('request_key'); $t->string('label'); $t->unsignedInteger('credits'); $t->unsignedInteger('amount'); $t->string('currency',3)->default('PHP');
            $t->string('provider'); $t->string('environment')->default('sandbox'); $t->string('provider_id')->nullable(); $t->text('checkout_url')->nullable();
            $t->string('status')->default('creating'); $t->timestamp('paid_at')->nullable(); $t->timestamp('checked_at')->nullable(); $t->timestamps();
            $t->unique(['provider','provider_id']); $t->unique(['user_id','request_key']);
        });
        Schema::table('credit_transactions',fn(Blueprint $t)=>$t->uuid('purchase_id')->nullable()->unique());
    }
    public function down(): void { throw new RuntimeException('Preserve payment history; restore a backup instead.'); }
};
