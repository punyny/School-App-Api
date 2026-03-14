<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaverequest', function (Blueprint $table): void {
            $table->date('end_date')->nullable()->after('start_date');
            $table->time('end_time')->nullable()->after('start_time');
            $table->date('return_date')->nullable()->after('end_date');
            $table->unsignedSmallInteger('total_days')->nullable()->after('return_date');
            $table->text('subject_ids')->nullable()->after('subject_id');
            $table->foreignId('approved_by')->nullable()->after('submitted_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('leaverequest', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'subject_ids', 'total_days', 'return_date', 'end_time', 'end_date']);
        });
    }
};
