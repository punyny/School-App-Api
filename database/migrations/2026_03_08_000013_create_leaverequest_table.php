<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaverequest', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->string('request_type', 100);
            $table->date('start_date');
            $table->time('start_time')->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('student_id', 'leaverequest_student_id_index');
            $table->index('subject_id', 'leaverequest_subject_id_index');
            $table->index('status', 'leaverequest_status_index');
            $table->index('start_date', 'leaverequest_start_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaverequest');
    }
};

