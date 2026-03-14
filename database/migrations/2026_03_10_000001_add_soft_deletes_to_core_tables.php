<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        Schema::table('classes', function (Blueprint $table): void {
            if (! Schema::hasColumn('classes', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        Schema::table('students', function (Blueprint $table): void {
            if (! Schema::hasColumn('students', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('classes', function (Blueprint $table): void {
            if (Schema::hasColumn('classes', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('students', function (Blueprint $table): void {
            if (Schema::hasColumn('students', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
