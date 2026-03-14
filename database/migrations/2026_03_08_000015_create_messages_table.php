<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->text('content');
            $table->dateTime('date');
            $table->timestamps();

            $table->index('sender_id', 'messages_sender_id_index');
            $table->index('receiver_id', 'messages_receiver_id_index');
            $table->index('class_id', 'messages_class_id_index');
            $table->index('date', 'messages_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

