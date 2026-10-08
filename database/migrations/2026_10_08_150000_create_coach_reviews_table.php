<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('coach_reviews', function (Blueprint $t) {
            $t->id(); $t->foreignId('chat_session_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('counselor_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedTinyInteger('rating'); $t->text('comment');
            $t->boolean('publish_consent')->default(false); $t->boolean('highlighted')->default(false);
            $t->timestamp('reviewed_at')->nullable(); $t->timestamps(); $t->softDeletes();
            $t->index(['counselor_id', 'deleted_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('coach_reviews'); }
};
