<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'name'], 'subjects_school_name_unique');
            $table->index('school_id', 'subjects_school_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};

