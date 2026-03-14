<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('content');
            $table->dateTime('date');
            $table->boolean('read_status')->default(false);

            $table->index('user_id', 'notification_user_id_index');
            $table->index('read_status', 'notification_read_status_index');
            $table->index('date', 'notification_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification');
    }
};

