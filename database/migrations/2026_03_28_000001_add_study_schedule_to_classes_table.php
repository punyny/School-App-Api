<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('classes')) {
            return;
        }

        Schema::table('classes', function (Blueprint $table): void {
            if (! Schema::hasColumn('classes', 'study_days')) {
                $table->json('study_days')->nullable()->after('room');
            }

            if (! Schema::hasColumn('classes', 'study_time_start')) {
                $table->time('study_time_start')->nullable()->after('study_days');
            }

            if (! Schema::hasColumn('classes', 'study_time_end')) {
                $table->time('study_time_end')->nullable()->after('study_time_start');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('classes')) {
            return;
        }

        Schema::table('classes', function (Blueprint $table): void {
            if (Schema::hasColumn('classes', 'study_time_end')) {
                $table->dropColumn('study_time_end');
            }

            if (Schema::hasColumn('classes', 'study_time_start')) {
                $table->dropColumn('study_time_start');
            }

            if (Schema::hasColumn('classes', 'study_days')) {
                $table->dropColumn('study_days');
            }
        });
    }
};

