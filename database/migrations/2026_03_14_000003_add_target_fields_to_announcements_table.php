<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            if (! Schema::hasColumn('announcements', 'target_role')) {
                $table->string('target_role', 20)->nullable()->after('class_id');
            }

            if (! Schema::hasColumn('announcements', 'target_user_id')) {
                $table->foreignId('target_user_id')
                    ->nullable()
                    ->after('target_role')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            if (Schema::hasColumn('announcements', 'target_user_id')) {
                $table->dropConstrainedForeignId('target_user_id');
            }

            if (Schema::hasColumn('announcements', 'target_role')) {
                $table->dropColumn('target_role');
            }
        });
    }
};

