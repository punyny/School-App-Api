<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'school_id')) {
                $table->foreignId('school_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('schools')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 30)->default('student')->after('name');
            }

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'password_hash')) {
                $table->string('password_hash')->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'address')) {
                $table->string('address')->nullable()->after('password_hash');
            }

            if (! Schema::hasColumn('users', 'bio')) {
                $table->text('bio')->nullable()->after('address');
            }

            if (! Schema::hasColumn('users', 'image_url')) {
                $table->string('image_url')->nullable()->after('bio');
            }

            if (! Schema::hasColumn('users', 'active')) {
                $table->boolean('active')->default(true)->after('image_url');
            }

            if (! Schema::hasColumn('users', 'last_login')) {
                $table->timestamp('last_login')->nullable()->after('active');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('school_id', 'users_school_id_index');
            $table->index('role', 'users_role_index');
            $table->unique(['school_id', 'email'], 'users_school_id_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_school_id_email_unique');
            $table->dropIndex('users_school_id_index');
            $table->dropIndex('users_role_index');
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'school_id')) {
                $table->dropConstrainedForeignId('school_id');
            }

            $dropColumns = [];
            foreach (['role', 'phone', 'password_hash', 'address', 'bio', 'image_url', 'active', 'last_login'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $dropColumns[] = $column;
                }
            }
            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};

