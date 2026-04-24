<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('substitute_teacher_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('original_teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('substitute_teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->time('time_start');
            $table->time('time_end');
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(
                ['class_id', 'subject_id', 'date', 'time_start', 'time_end'],
                'substitute_assignments_session_unique'
            );
            $table->index(['substitute_teacher_id', 'date'], 'substitute_assignments_substitute_date_idx');
            $table->index(['original_teacher_id', 'date'], 'substitute_assignments_original_date_idx');
            $table->index(['school_id', 'date'], 'substitute_assignments_school_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('substitute_teacher_assignments');
    }
};
