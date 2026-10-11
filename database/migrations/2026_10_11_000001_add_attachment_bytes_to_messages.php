<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('attachment_bytes')->nullable();
            $table->index(['sender_id', 'created_at'], 'messages_sender_upload_usage');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_sender_upload_usage');
            $table->dropColumn('attachment_bytes');
        });
    }
};
