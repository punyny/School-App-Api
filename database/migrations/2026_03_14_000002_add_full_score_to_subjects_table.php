<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            if (! Schema::hasColumn('subjects', 'full_score')) {
                $table->decimal('full_score', 8, 2)->default(100)->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            if (Schema::hasColumn('subjects', 'full_score')) {
                $table->dropColumn('full_score');
            }
        });
    }
};
