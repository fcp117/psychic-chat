<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tarot_cards', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name', 100);
            $table->string('category', 60);
            $table->string('keywords', 255);
            $table->text('meaning');
            $table->text('guidance');
            $table->text('reflection');
            $table->string('image_path');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        // Stable lock rows serialize draws and guest-to-account claims across requests.
        Schema::create('tarot_readers', function (Blueprint $table) {
            $table->string('id', 80)->primary();
            $table->timestamps();
        });
        Schema::create('tarot_draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_id', 80)->nullable();
            $table->foreignId('tarot_card_id')->constrained()->restrictOnDelete();
            $table->uuid('request_token')->unique();
            $table->date('draw_date');
            $table->json('reading');
            $table->timestamps();
            $table->index(['user_id', 'draw_date']);
            $table->index(['guest_id', 'draw_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarot_draws');
        Schema::dropIfExists('tarot_readers');
        Schema::dropIfExists('tarot_cards');
    }
};
