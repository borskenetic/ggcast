<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ebooks', function (Blueprint $table) {
            $table->string('resource_type')->default('Electronic')->after('link');
            $table->string('publication_scope')->nullable()->after('resource_type');
            $table->string('frequency')->nullable()->after('publication_scope');
            $table->string('subscription_type')->nullable()->after('frequency');
        });
    }

    public function down(): void
    {
        Schema::table('ebooks', function (Blueprint $table) {
            $table->dropColumn([
                'resource_type',
                'publication_scope',
                'frequency',
                'subscription_type',
            ]);
        });
    }
};
