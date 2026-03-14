<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->string('school_code', 100)->nullable()->after('name');
            $table->unique('school_code', 'schools_school_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropUnique('schools_school_code_unique');
            $table->dropColumn('school_code');
        });
    }
};
