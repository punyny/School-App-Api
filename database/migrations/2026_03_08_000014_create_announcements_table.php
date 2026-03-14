<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('content');
            $table->date('date');
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->timestamps();

            $table->index('school_id', 'announcements_school_id_index');
            $table->index('class_id', 'announcements_class_id_index');
            $table->index('date', 'announcements_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};

