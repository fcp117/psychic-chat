<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};
return new class extends Migration {
    public function up(): void {
        Schema::create('social_links', function (Blueprint $table) {
            $table->string('platform')->primary();
            $table->string('url', 2048)->nullable();
            $table->timestamps();
        });
        foreach (['facebook'=>'https://www.facebook.com/Intuitionisland/', 'instagram'=>'https://www.instagram.com/intuitionisland_/'] as $platform=>$url) {
            DB::table('social_links')->insert(compact('platform','url') + ['created_at'=>now(),'updated_at'=>now()]);
        }
    }
    public function down(): void { Schema::dropIfExists('social_links'); }
};
