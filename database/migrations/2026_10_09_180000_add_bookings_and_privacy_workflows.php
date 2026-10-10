<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('coach_availability',function(Blueprint $t){$t->id();$t->foreignId('coach_id')->constrained('users');$t->timestamp('starts_at')->index();$t->timestamp('ends_at');$t->string('timezone');$t->timestamps();});
  Schema::create('bookings',function(Blueprint $t){$t->id();$t->foreignId('client_id')->constrained('users');$t->foreignId('coach_id')->constrained('users');$t->timestamp('starts_at')->index();$t->timestamp('ends_at');$t->unsignedInteger('minutes');$t->string('timezone');$t->string('status')->default('booked')->index();$t->timestamp('client_joined_at')->nullable();$t->timestamp('coach_joined_at')->nullable();$t->text('review_reason')->nullable();$t->bigInteger('penalty_units')->default(0);$t->foreignId('reviewed_by')->nullable()->constrained('users');$t->timestamp('reviewed_at')->nullable();$t->timestamps();});
  Schema::create('booking_holds',function(Blueprint $t){$t->id();$t->foreignId('booking_id')->constrained();$t->foreignId('lot_id')->constrained('minute_lots');$t->bigInteger('original_units');$t->bigInteger('remaining_units');$t->timestamp('released_at')->nullable();$t->unique(['booking_id','lot_id']);});
  Schema::table('chat_sessions',fn(Blueprint $t)=>$t->foreignId('booking_id')->nullable()->unique()->constrained());
  Schema::table('chat_sessions',fn(Blueprint $t)=>$t->timestamp('hard_stop_at')->nullable());
  Schema::table('users',fn(Blueprint $t)=>$t->timestamp('closed_at')->nullable());
  Schema::create('privacy_requests',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained();$t->string('kind');$t->foreignId('chat_session_id')->nullable()->constrained();$t->unsignedBigInteger('through_message_id')->default(0);$t->string('status')->default('pending');$t->text('reason');$t->text('decision_reason')->nullable();$t->foreignId('reviewed_by')->nullable()->constrained('users');$t->timestamp('reviewed_at')->nullable();$t->timestamps();});
  Schema::create('transcript_notices',function(Blueprint $t){$t->id();$t->foreignId('client_id')->unique()->constrained('users');$t->timestamp('activity_at');$t->timestamp('notified_at')->nullable();$t->timestamp('delete_after')->nullable();$t->unsignedBigInteger('through_message_id');$t->timestamps();});
  Schema::create('private_file_deletions',function(Blueprint $t){$t->id();$t->string('path')->unique();$t->timestamps();});
 }
 public function down(): void {throw new RuntimeException('Back up and restore explicitly; booking reservations and privacy audit records must not be dropped.');}
};
