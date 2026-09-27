<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            // Keep historic purchases intact; only the purchasable catalogue changes.
            DB::table('credit_shop_settings')->where('id', 1)->update([
                'custom_enabled' => false,
                'minimum_amount' => 9900,
                'updated_at' => now(),
            ]);
            DB::table('credit_packages')->where('active', true)->update(['active' => false, 'updated_at' => now()]);
            foreach ([
                ['name' => 'Starter', 'credits' => 1, 'amount' => 9900],
                ['name' => 'Most popular', 'credits' => 3, 'amount' => 24900],
                ['name' => 'Regular', 'credits' => 6, 'amount' => 44900],
                ['name' => 'Best value', 'credits' => 12, 'amount' => 79900],
            ] as $package) {
                DB::table('credit_packages')->updateOrInsert(
                    ['name' => $package['name']],
                    $package + ['active' => true, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });
    }

    public function down(): void
    {
        DB::table('credit_shop_settings')->where('id', 1)->update(['custom_enabled' => true, 'minimum_amount' => 100, 'updated_at' => now()]);
        DB::table('credit_packages')->whereIn('name', ['Starter', 'Most popular', 'Regular', 'Best value'])->delete();
    }
};
