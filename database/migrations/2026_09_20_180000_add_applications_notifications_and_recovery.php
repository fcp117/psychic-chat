<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('counselor_applications',function(Blueprint $t) {
   $t->id(); $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
   $t->text('biography'); $t->string('specialties',200); $t->string('languages',200);
   $t->unsignedTinyInteger('years_experience'); $t->text('qualifications'); $t->text('availability');
   $t->string('status')->default('pending')->index(); $t->text('review_note')->nullable();
   $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
  });
  Schema::create('app_notifications',function(Blueprint $t) {
   $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->string('event_key',160);
   $t->string('title',160); $t->text('body'); $t->string('url'); $t->timestamp('read_at')->nullable(); $t->timestamps();
   $t->unique(['user_id','event_key']); $t->index(['user_id','read_at']);
  });
  Schema::table('messages',function(Blueprint $t) { $t->uuid('request_key')->nullable(); $t->unique(['sender_id','request_key']); });
  Schema::create('system_incidents',function(Blueprint $t) {
   $t->uuid('id')->primary(); $t->string('type'); $t->string('route')->nullable(); $t->string('method',10)->nullable(); $t->timestamp('created_at');
  });
 }
 public function down(): void {
  Schema::dropIfExists('system_incidents'); Schema::table('messages',function(Blueprint $t){$t->dropUnique(['sender_id','request_key']);$t->dropColumn('request_key');});
  Schema::dropIfExists('app_notifications'); Schema::dropIfExists('counselor_applications');
 }
};
