<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('messages', 'class_target')) {
                $table->string('class_target', 32)
                    ->nullable()
                    ->after('class_id')
                    ->default('students_parents');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table): void {
            if (Schema::hasColumn('messages', 'class_target')) {
                $table->dropColumn('class_target');
            }
        });
    }
};

