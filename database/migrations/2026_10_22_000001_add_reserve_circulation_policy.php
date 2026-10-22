<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fine_settings', function (Blueprint $table) {
            $table->decimal('reserve_fine_per_day', 8, 2)->nullable()->after('loan_duration_days');
            $table->decimal('reserve_max_fine', 8, 2)->nullable()->after('reserve_fine_per_day');
            $table->integer('reserve_grace_period_days')->nullable()->after('reserve_max_fine');
            $table->integer('reserve_loan_duration_days')->nullable()->after('reserve_grace_period_days');
        });

        DB::table('fine_settings')->update([
            'reserve_fine_per_day' => DB::raw('fine_per_day'),
            'reserve_max_fine' => DB::raw('max_fine'),
            'reserve_grace_period_days' => DB::raw('grace_period_days'),
            'reserve_loan_duration_days' => 1,
        ]);

        Schema::table('book_logs', function (Blueprint $table) {
            $table->boolean('is_reserve')->default(false)->after('circulation_type');
        });
    }

    public function down(): void
    {
        Schema::table('book_logs', function (Blueprint $table) {
            $table->dropColumn('is_reserve');
        });

        Schema::table('fine_settings', function (Blueprint $table) {
            $table->dropColumn([
                'reserve_fine_per_day',
                'reserve_max_fine',
                'reserve_grace_period_days',
                'reserve_loan_duration_days',
            ]);
        });
    }
};
