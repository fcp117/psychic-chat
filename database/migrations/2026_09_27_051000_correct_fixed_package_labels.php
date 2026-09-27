<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach ([
            'Starter — 1 hour' => 'Starter',
            'Most popular — 3 hours' => 'Most popular',
            'Regular — 6 hours' => 'Regular',
            'Best value — 12 hours' => 'Best value',
        ] as $old => $new) {
            DB::table('credit_packages')->where('name', $old)->update(['name' => $new, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach ([
            'Starter' => 'Starter — 1 hour',
            'Most popular' => 'Most popular — 3 hours',
            'Regular' => 'Regular — 6 hours',
            'Best value' => 'Best value — 12 hours',
        ] as $old => $new) {
            DB::table('credit_packages')->where('name', $old)->update(['name' => $new, 'updated_at' => now()]);
        }
    }
};
