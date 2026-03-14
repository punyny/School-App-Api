<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('seen_at');
            $table->timestamps();

            $table->unique(['message_id', 'user_id'], 'message_reads_message_user_unique');
            $table->index(['user_id', 'seen_at'], 'message_reads_user_seen_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reads');
    }
};
