<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidentreport', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->text('description');
            $table->date('date');
            $table->string('type', 100)->nullable();
            $table->boolean('acknowledged')->default(false);
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('student_id', 'incidentreport_student_id_index');
            $table->index('type', 'incidentreport_type_index');
            $table->index('acknowledged', 'incidentreport_acknowledged_index');
            $table->index('date', 'incidentreport_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidentreport');
    }
};

