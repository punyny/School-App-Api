<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('grade_level', 50)->nullable();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'name', 'grade_level'], 'classes_school_name_grade_unique');
            $table->index('school_id', 'classes_school_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};

