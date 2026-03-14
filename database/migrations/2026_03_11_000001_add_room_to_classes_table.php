<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            if (! Schema::hasColumn('classes', 'room')) {
                $table->string('room', 50)->nullable()->after('grade_level');
                $table->index('room', 'classes_room_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            if (Schema::hasColumn('classes', 'room')) {
                $table->dropIndex('classes_room_index');
                $table->dropColumn('room');
            }
        });
    }
};

