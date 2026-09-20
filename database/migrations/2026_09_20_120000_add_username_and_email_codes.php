<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', fn(Blueprint $t) => $t->string('username',40)->nullable()->unique());
        foreach (DB::table('users')->select('id')->get() as $user) DB::table('users')->where('id',$user->id)->update(['username'=>'user'.$user->id]);
        Schema::create('email_verification_codes', function(Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $t->string('email'); $t->string('code_hash'); $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at'); $t->timestamp('sent_at');
        });
    }
    public function down(): void {
        Schema::dropIfExists('email_verification_codes');
        Schema::table('users',fn(Blueprint $t)=>$t->dropColumn('username'));
    }
};
