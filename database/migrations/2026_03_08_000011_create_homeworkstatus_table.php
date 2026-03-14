<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homeworkstatus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_id')->constrained('homeworks')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('status', 30);
            $table->date('completion_date')->nullable();
            $table->timestamps();

            $table->unique(['homework_id', 'student_id'], 'homeworkstatus_homework_student_unique');
            $table->index('status', 'homeworkstatus_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeworkstatus');
    }
};

