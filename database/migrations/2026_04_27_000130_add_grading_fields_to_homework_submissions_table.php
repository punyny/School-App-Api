<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework_submissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('homework_submissions', 'teacher_score')) {
                $table->decimal('teacher_score', 8, 2)->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('homework_submissions', 'teacher_score_max')) {
                $table->decimal('teacher_score_max', 8, 2)->nullable()->after('teacher_score');
            }
            if (! Schema::hasColumn('homework_submissions', 'score_weight_percent')) {
                $table->decimal('score_weight_percent', 5, 2)->nullable()->after('teacher_score_max');
            }
            if (! Schema::hasColumn('homework_submissions', 'score_assessment_type')) {
                $table->string('score_assessment_type', 20)->nullable()->after('score_weight_percent');
            }
            if (! Schema::hasColumn('homework_submissions', 'score_month')) {
                $table->unsignedTinyInteger('score_month')->nullable()->after('score_assessment_type');
            }
            if (! Schema::hasColumn('homework_submissions', 'score_semester')) {
                $table->unsignedTinyInteger('score_semester')->nullable()->after('score_month');
            }
            if (! Schema::hasColumn('homework_submissions', 'score_academic_year')) {
                $table->string('score_academic_year', 20)->nullable()->after('score_semester');
            }
            if (! Schema::hasColumn('homework_submissions', 'teacher_feedback')) {
                $table->text('teacher_feedback')->nullable()->after('score_academic_year');
            }
            if (! Schema::hasColumn('homework_submissions', 'graded_at')) {
                $table->timestamp('graded_at')->nullable()->after('teacher_feedback');
            }
            if (! Schema::hasColumn('homework_submissions', 'graded_by_user_id')) {
                $table->foreignId('graded_by_user_id')
                    ->nullable()
                    ->after('graded_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        Schema::table('homework_submissions', function (Blueprint $table): void {
            $table->index(
                ['student_id', 'score_assessment_type', 'score_month', 'score_semester', 'score_academic_year'],
                'homework_submissions_score_bucket_index'
            );
            $table->index('graded_by_user_id', 'homework_submissions_graded_by_user_index');
        });
    }

    public function down(): void
    {
        Schema::table('homework_submissions', function (Blueprint $table): void {
            $table->dropIndex('homework_submissions_score_bucket_index');
            $table->dropIndex('homework_submissions_graded_by_user_index');
        });

        Schema::table('homework_submissions', function (Blueprint $table): void {
            if (Schema::hasColumn('homework_submissions', 'graded_by_user_id')) {
                $table->dropConstrainedForeignId('graded_by_user_id');
            }

            $dropColumns = [];
            foreach ([
                'teacher_score',
                'teacher_score_max',
                'score_weight_percent',
                'score_assessment_type',
                'score_month',
                'score_semester',
                'score_academic_year',
                'teacher_feedback',
                'graded_at',
            ] as $column) {
                if (Schema::hasColumn('homework_submissions', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
