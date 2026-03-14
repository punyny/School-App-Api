<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_class', function (Blueprint $table) {
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['teacher_id', 'class_id', 'subject_id'], 'teacher_class_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_class');
    }
};

