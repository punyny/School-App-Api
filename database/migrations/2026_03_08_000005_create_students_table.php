<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('grade', 20)->nullable();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('user_id', 'students_user_id_unique');
            $table->index('class_id', 'students_class_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};

