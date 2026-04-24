<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'telegram_chat_id')) {
                $table->string('telegram_chat_id', 64)
                    ->nullable()
                    ->after('phone');
                $table->index('telegram_chat_id', 'users_telegram_chat_id_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'telegram_chat_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_telegram_chat_id_index');
            $table->dropColumn('telegram_chat_id');
        });
    }
};
