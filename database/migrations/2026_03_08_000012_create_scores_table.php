<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->decimal('exam_score', 8, 2)->default(0);
            $table->decimal('total_score', 8, 2)->default(0);
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedTinyInteger('quarter')->nullable();
            $table->string('period', 50)->nullable();
            $table->string('grade', 5)->nullable();
            $table->timestamps();

            $table->index('student_id', 'scores_student_id_index');
            $table->index('class_id', 'scores_class_id_index');
            $table->index('subject_id', 'scores_subject_id_index');
            $table->index(['student_id', 'month', 'quarter', 'period'], 'scores_student_month_quarter_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};

