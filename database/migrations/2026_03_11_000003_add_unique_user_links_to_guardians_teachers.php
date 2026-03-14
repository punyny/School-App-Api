<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('guardians')) {
            Schema::table('guardians', function (Blueprint $table): void {
                if (Schema::hasColumn('guardians', 'user_id')) {
                    $table->unique('user_id', 'guardians_user_id_unique');
                }
            });
        }

        if (Schema::hasTable('teachers')) {
            Schema::table('teachers', function (Blueprint $table): void {
                if (Schema::hasColumn('teachers', 'user_id')) {
                    $table->unique('user_id', 'teachers_user_id_unique');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('guardians')) {
            Schema::table('guardians', function (Blueprint $table): void {
                $table->dropUnique('guardians_user_id_unique');
            });
        }

        if (Schema::hasTable('teachers')) {
            Schema::table('teachers', function (Blueprint $table): void {
                $table->dropUnique('teachers_user_id_unique');
            });
        }
    }
};
