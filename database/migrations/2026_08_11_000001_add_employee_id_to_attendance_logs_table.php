<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_logs', 'employee_id')) {
                $table->unsignedBigInteger('employee_id')->nullable()->after('student_id');
            }
        });

        // Allow employee-only attendance rows (no student).
        if (Schema::hasColumn('attendance_logs', 'student_id')) {
            Schema::table('attendance_logs', function (Blueprint $table) {
                $table->string('student_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_logs', 'employee_id')) {
                $table->dropColumn('employee_id');
            }
        });

        if (Schema::hasColumn('attendance_logs', 'student_id')) {
            Schema::table('attendance_logs', function (Blueprint $table) {
                $table->string('student_id')->nullable(false)->change();
            });
        }
    }
};
