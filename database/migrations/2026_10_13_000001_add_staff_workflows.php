<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('users',function(Blueprint $t){$t->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();});
  Schema::table('user_reports',function(Blueprint $t){
   $t->string('proposed_action')->nullable();$t->text('support_note')->nullable();$t->text('manager_note')->nullable();
   $t->foreignId('support_id')->nullable()->constrained('users');$t->foreignId('manager_id')->nullable()->constrained('users');
   $t->timestamp('support_reviewed_at')->nullable();$t->timestamp('manager_reviewed_at')->nullable();
  });
  Schema::table('privacy_requests',function(Blueprint $t){
   $t->text('support_note')->nullable();$t->foreignId('support_id')->nullable()->constrained('users');$t->timestamp('support_reviewed_at')->nullable();
  });
  Schema::create('staff_emails',function(Blueprint $t){
   $t->id();$t->foreignId('sender_id')->constrained('users');$t->foreignId('recipient_id')->constrained('users');
   $t->uuid('request_key');$t->string('template');$t->string('recipient_email');$t->string('subject');$t->text('body');
   $t->string('status')->default('queued');$t->timestamp('sent_at')->nullable();$t->timestamps();
   $t->unique(['sender_id','request_key','recipient_id']);
  });
 }
 public function down(): void {
  Schema::dropIfExists('staff_emails');
  Schema::table('privacy_requests',function(Blueprint $t){$t->dropConstrainedForeignId('support_id');$t->dropColumn(['support_note','support_reviewed_at']);});
  Schema::table('user_reports',function(Blueprint $t){$t->dropConstrainedForeignId('support_id');$t->dropConstrainedForeignId('manager_id');$t->dropColumn(['proposed_action','support_note','manager_note','support_reviewed_at','manager_reviewed_at']);});
  Schema::table('users',fn(Blueprint $t)=>$t->dropConstrainedForeignId('manager_id'));
 }
};
