<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('messages',function(Blueprint $t){$t->string('attachment_path')->nullable();$t->string('attachment_name')->nullable();$t->string('attachment_mime')->nullable();$t->timestamp('read_at')->nullable();});
  Schema::create('coach_notes',function(Blueprint $t){$t->id();$t->foreignId('client_id')->constrained('users')->cascadeOnDelete();$t->foreignId('counselor_id')->constrained('users')->cascadeOnDelete();$t->text('body');$t->timestamps();$t->unique(['client_id','counselor_id']);});
  Schema::create('reading_invitations',function(Blueprint $t){$t->id();$t->foreignId('chat_session_id')->constrained()->cascadeOnDelete();$t->unsignedInteger('rate');$t->timestamp('expires_at');$t->timestamp('confirmed_at')->nullable();$t->timestamps();});
  Schema::create('support_threads',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('category');$t->timestamps();$t->unique(['user_id','category']);});
  Schema::create('support_messages',function(Blueprint $t){$t->id();$t->foreignId('support_thread_id')->constrained()->cascadeOnDelete();$t->foreignId('sender_id')->constrained('users')->cascadeOnDelete();$t->text('body');$t->uuid('request_key')->unique();$t->timestamp('read_at')->nullable();$t->timestamps();});
 }
 public function down(): void {
  Schema::dropIfExists('support_messages');Schema::dropIfExists('support_threads');Schema::dropIfExists('reading_invitations');Schema::dropIfExists('coach_notes');
  Schema::table('messages',fn(Blueprint $t)=>$t->dropColumn(['attachment_path','attachment_name','attachment_mime','read_at']));
 }
};
