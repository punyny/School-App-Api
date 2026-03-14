<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'user_code')) {
                $table->string('user_code', 100)->nullable()->after('role');
            }
            if (! Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name', 100)->nullable()->after('user_code');
            }
            if (! Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name', 100)->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('users', 'khmer_name')) {
                $table->string('khmer_name', 255)->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('users', 'gender')) {
                $table->string('gender', 20)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'dob')) {
                $table->date('dob')->nullable()->after('gender');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'user_code')) {
                return;
            }

            $table->index('user_code', 'users_user_code_index');
            $table->unique(['school_id', 'user_code'], 'users_school_id_user_code_unique');
        });

        Schema::table('students', function (Blueprint $table): void {
            if (! Schema::hasColumn('students', 'student_code')) {
                $table->string('student_code', 100)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('students', 'parent_name')) {
                $table->string('parent_name', 255)->nullable()->after('grade');
            }
        });

        Schema::table('students', function (Blueprint $table): void {
            if (Schema::hasColumn('students', 'student_code')) {
                $table->index('student_code', 'students_student_code_index');
                $table->unique('student_code', 'students_student_code_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            if (Schema::hasColumn('students', 'student_code')) {
                $table->dropUnique('students_student_code_unique');
                $table->dropIndex('students_student_code_index');
            }
        });

        Schema::table('students', function (Blueprint $table): void {
            $dropColumns = [];
            foreach (['student_code', 'parent_name'] as $column) {
                if (Schema::hasColumn('students', $column)) {
                    $dropColumns[] = $column;
                }
            }
            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'user_code')) {
                $table->dropUnique('users_school_id_user_code_unique');
                $table->dropIndex('users_user_code_index');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $dropColumns = [];
            foreach (['user_code', 'first_name', 'last_name', 'khmer_name', 'gender', 'dob'] as $column) {
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
