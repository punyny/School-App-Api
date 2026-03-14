<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->date('date');
            $table->time('time_start');
            $table->time('time_end')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['student_id', 'class_id', 'date', 'time_start'], 'attendance_student_class_date_start_unique');
            $table->index(['class_id', 'date'], 'attendance_class_date_index');
            $table->index(['student_id', 'date'], 'attendance_student_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};

