<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('users', fn(Blueprint $table) => $table->boolean('music_muted')->default(false));
  Schema::table('coach_availability', fn(Blueprint $table) => $table->boolean('is_available')->default(true));
 }
 public function down(): void {
  Schema::table('users', fn(Blueprint $table) => $table->dropColumn('music_muted'));
  Schema::table('coach_availability', fn(Blueprint $table) => $table->dropColumn('is_available'));
 }
};
