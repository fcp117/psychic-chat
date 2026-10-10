<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  Schema::create('coach_site_settings', function(Blueprint $table) {
   $table->id();
   $table->boolean('rainbow_only')->default(false);
   $table->boolean('applications_open')->default(true);
   $table->foreignId('rainbow_user_id')->nullable()->constrained('users')->nullOnDelete();
   $table->timestamps();
  });
  DB::table('coach_site_settings')->insert(['id'=>1,'rainbow_only'=>false,'applications_open'=>true,'created_at'=>now(),'updated_at'=>now()]);
 }
 public function down(): void { Schema::dropIfExists('coach_site_settings'); }
};
