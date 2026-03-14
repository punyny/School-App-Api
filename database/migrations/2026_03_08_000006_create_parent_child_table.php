<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_child', function (Blueprint $table) {
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['parent_id', 'student_id'], 'parent_child_parent_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_child');
    }
};

