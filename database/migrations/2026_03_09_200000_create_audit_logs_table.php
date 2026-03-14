<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('actor_name', 100);
            $table->string('actor_role', 50);
            $table->string('method', 10);
            $table->string('endpoint');
            $table->string('action');
            $table->string('resource_type', 100)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('request_payload')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamps();

            $table->index(['actor_id', 'created_at']);
            $table->index(['school_id', 'created_at']);
            $table->index(['actor_role', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
            $table->index(['method', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

