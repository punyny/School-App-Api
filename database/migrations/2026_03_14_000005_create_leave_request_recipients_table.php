<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_request_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leaverequest')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('recipient_role', 30)->nullable();
            $table->timestamps();

            $table->unique(['leave_request_id', 'user_id'], 'leave_request_recipient_unique');
            $table->index('user_id', 'leave_request_recipient_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_recipients');
    }
};
