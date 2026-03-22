<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance')) {
            return;
        }

        if (! Schema::hasColumn('attendance', 'subject_id')) {
            Schema::table('attendance', function (Blueprint $table): void {
                $table->foreignId('subject_id')->nullable()->after('class_id')->constrained('subjects')->nullOnDelete();
                $table->index(['subject_id', 'date'], 'attendance_subject_date_index');
            });
        }

        try {
            Schema::table('attendance', function (Blueprint $table): void {
                $table->dropUnique('attendance_student_class_date_start_unique');
            });
        } catch (\Throwable) {
            // Ignore when the legacy index is already gone.
        }

        try {
            Schema::table('attendance', function (Blueprint $table): void {
                $table->unique(
                    ['student_id', 'class_id', 'date', 'time_start', 'subject_id'],
                    'attendance_student_class_date_start_subject_unique'
                );
            });
        } catch (\Throwable) {
            // Ignore when the new index already exists.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance')) {
            return;
        }

        try {
            Schema::table('attendance', function (Blueprint $table): void {
                $table->dropUnique('attendance_student_class_date_start_subject_unique');
            });
        } catch (\Throwable) {
            // Ignore when the index is already removed.
        }

        try {
            Schema::table('attendance', function (Blueprint $table): void {
                $table->unique(
                    ['student_id', 'class_id', 'date', 'time_start'],
                    'attendance_student_class_date_start_unique'
                );
            });
        } catch (\Throwable) {
            // Ignore when the legacy index already exists.
        }

        if (Schema::hasColumn('attendance', 'subject_id')) {
            Schema::table('attendance', function (Blueprint $table): void {
                $table->dropIndex('attendance_subject_date_index');
                $table->dropConstrainedForeignId('subject_id');
            });
        }
    }
};
