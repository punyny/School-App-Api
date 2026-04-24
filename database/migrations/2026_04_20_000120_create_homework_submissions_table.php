<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_id')->constrained('homeworks')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->text('answer_text')->nullable();
            $table->json('file_attachments')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['homework_id', 'student_id'], 'homework_submissions_homework_student_unique');
            $table->index('submitted_at', 'homework_submissions_submitted_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_submissions');
    }
};
