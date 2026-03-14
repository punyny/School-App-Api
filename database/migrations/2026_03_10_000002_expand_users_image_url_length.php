<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'image_url')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->text('image_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'image_url')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('image_url', 255)->nullable()->change();
        });
    }
};
