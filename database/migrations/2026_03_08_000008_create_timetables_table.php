<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('day_of_week', 20);
            $table->time('time_start');
            $table->time('time_end');
            $table->timestamps();

            $table->index('class_id', 'timetables_class_id_index');
            $table->index('subject_id', 'timetables_subject_id_index');
            $table->index('teacher_id', 'timetables_teacher_id_index');
            $table->index(['class_id', 'day_of_week', 'time_start'], 'timetables_class_day_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetables');
    }
};

