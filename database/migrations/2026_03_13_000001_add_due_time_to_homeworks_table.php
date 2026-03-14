<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homeworks', function (Blueprint $table): void {
            if (! Schema::hasColumn('homeworks', 'due_time')) {
                $table->time('due_time')->nullable()->after('due_date');
                $table->index(['due_date', 'due_time'], 'homeworks_due_date_due_time_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('homeworks', function (Blueprint $table): void {
            if (Schema::hasColumn('homeworks', 'due_time')) {
                $table->dropIndex('homeworks_due_date_due_time_index');
                $table->dropColumn('due_time');
            }
        });
    }
};
