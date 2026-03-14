<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homeworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('question')->nullable();
            $table->date('due_date')->nullable();
            $table->json('file_attachments')->nullable();
            $table->timestamps();

            $table->index('class_id', 'homeworks_class_id_index');
            $table->index('subject_id', 'homeworks_subject_id_index');
            $table->index('due_date', 'homeworks_due_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeworks');
    }
};

