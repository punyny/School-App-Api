<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mediable_type', 150);
            $table->unsignedBigInteger('mediable_id');
            $table->string('category', 50)->default('general');
            $table->string('disk', 50)->default('public');
            $table->string('directory', 255)->nullable();
            $table->string('path', 1024);
            $table->text('url');
            $table->string('original_name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['mediable_type', 'mediable_id'], 'media_mediable_index');
            $table->index(['category', 'is_primary'], 'media_category_primary_index');
            $table->index('school_id', 'media_school_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
