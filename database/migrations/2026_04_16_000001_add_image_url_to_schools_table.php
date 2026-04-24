<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('schools', 'image_url')) {
            Schema::table('schools', function (Blueprint $table): void {
                $table->text('image_url')->nullable()->after('location');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('schools', 'image_url')) {
            Schema::table('schools', function (Blueprint $table): void {
                $table->dropColumn('image_url');
            });
        }
    }
};
