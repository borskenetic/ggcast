<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_print_jobs', function (Blueprint $table) {
            $table->id();
            $table->json('payload');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->index(['printed_at', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_print_jobs');
    }
};
