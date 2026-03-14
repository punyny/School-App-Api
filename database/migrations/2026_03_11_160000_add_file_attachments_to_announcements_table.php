<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->json('file_attachments')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropColumn('file_attachments');
        });
    }
};
