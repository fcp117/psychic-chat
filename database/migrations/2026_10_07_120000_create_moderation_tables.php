<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) { $t->string('last_seen_ip',45)->nullable(); });
        Schema::create('user_reports', function (Blueprint $t) {
            $t->id(); $t->foreignId('reporter_id')->constrained('users'); $t->foreignId('reported_id')->constrained('users');
            $t->foreignId('chat_session_id')->constrained(); $t->string('reason'); $t->text('details');
            $t->string('reported_ip',45)->nullable(); $t->string('status')->default('open')->index();
            $t->text('review_note')->nullable(); $t->foreignId('reviewer_id')->nullable()->constrained('users'); $t->timestamps();
        });
        Schema::create('blocked_ips', function (Blueprint $t) {
            $t->id(); $t->string('ip',45)->unique(); $t->foreignId('report_id')->constrained('user_reports');
            $t->foreignId('blocked_by')->constrained('users'); $t->text('reason'); $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('blocked_ips'); Schema::dropIfExists('user_reports');
        Schema::table('users', fn(Blueprint $t) => $t->dropColumn('last_seen_ip'));
    }
};
