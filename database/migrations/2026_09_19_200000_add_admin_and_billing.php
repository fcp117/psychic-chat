<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('user')->change();
            $t->bigInteger('credit_units')->default(0);
            $t->unsignedInteger('rate_per_hour')->nullable();
            $t->boolean('is_approved')->default(false);
            $t->boolean('is_suspended')->default(false);
        });
        DB::table('users')->update(['credit_units' => DB::raw('available_credits * 3600'), 'rate_per_hour' => DB::raw('rate_per_minute * 60')]);
        DB::table('users')->where('role', 'client')->update(['role' => 'user']);
        DB::table('users')->where('role', 'counselor')->update(['is_approved' => true]);
        Schema::create('billing_settings', function (Blueprint $t) {
            $t->id(); $t->unsignedInteger('default_rate')->default(60);
            $t->unsignedInteger('minimum_credits')->default(1);
            $t->unsignedInteger('disconnect_seconds')->default(30); $t->timestamps();
        });
        DB::table('billing_settings')->insert(['id'=>1,'default_rate'=>60,'minimum_credits'=>1,'disconnect_seconds'=>30,'created_at'=>now(),'updated_at'=>now()]);
        Schema::table('chat_sessions', function (Blueprint $t) {
            $t->unsignedInteger('agreed_rate')->nullable();
            $t->unsignedInteger('disconnect_seconds')->default(30);
            $t->timestamp('agreed_at')->nullable();
            $t->timestamp('client_seen_at')->nullable(); $t->timestamp('counselor_seen_at')->nullable();
            $t->unsignedBigInteger('billed_units')->default(0);
            $t->unsignedInteger('billed_seconds')->default(0);
            $t->string('end_reason')->nullable();
        });
        // Legacy sessions have no paid agreement: archive them without charging.
        DB::table('chat_sessions')->whereIn('status',['active','pending'])->update(['status'=>'completed','ended_at'=>now(),'end_reason'=>'legacy_unbilled']);
        Schema::create('counselor_preferences', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('counselor_id')->constrained('users')->cascadeOnDelete();
            $t->boolean('show_rate_notice')->default(true); $t->unsignedInteger('accepted_rate')->nullable();
            $t->timestamps(); $t->unique(['user_id','counselor_id']);
        });
        Schema::create('credit_transactions', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('counselor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('chat_session_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kind'); $t->bigInteger('amount_units'); $t->bigInteger('balance_units');
            $t->bigInteger('earning_units')->default(0); $t->text('reason'); $t->timestamps();
        });
        foreach (DB::table('users')->get() as $user) {
            DB::table('credit_transactions')->insert(['user_id'=>$user->id,'kind'=>'opening','amount_units'=>$user->credit_units,'balance_units'=>$user->credit_units,'earning_units'=>0,'reason'=>'Balance carried forward before billing was enabled','created_at'=>now(),'updated_at'=>now()]);
        }
        Schema::create('admin_audits', function (Blueprint $t) {
            $t->id(); $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action'); $t->string('target'); $t->json('before')->nullable(); $t->json('after')->nullable(); $t->timestamps();
        });
        Schema::create('forecasts', function (Blueprint $t) {
            $t->id(); $t->string('title'); $t->text('body'); $t->timestamp('published_at')->nullable(); $t->timestamps();
        });
    }
    public function down(): void {
        throw new RuntimeException('Billing history must be preserved. Restore a database backup instead of rolling back this migration.');
    }
};
