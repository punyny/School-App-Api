<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scores', function (Blueprint $table): void {
            if (! Schema::hasColumn('scores', 'assessment_type')) {
                $table->string('assessment_type', 20)->default('monthly')->after('total_score');
            }
            if (! Schema::hasColumn('scores', 'semester')) {
                $table->unsignedTinyInteger('semester')->nullable()->after('month');
            }
            if (! Schema::hasColumn('scores', 'academic_year')) {
                $table->string('academic_year', 20)->nullable()->after('semester');
            }
            if (! Schema::hasColumn('scores', 'rank_in_class')) {
                $table->unsignedInteger('rank_in_class')->nullable()->after('grade');
            }
        });

        Schema::table('scores', function (Blueprint $table): void {
            if (Schema::hasColumn('scores', 'assessment_type')) {
                $table->index(
                    ['class_id', 'subject_id', 'assessment_type', 'academic_year', 'month', 'semester'],
                    'scores_class_subject_assessment_window_index'
                );
                $table->index(
                    ['student_id', 'assessment_type', 'academic_year'],
                    'scores_student_assessment_year_index'
                );
                $table->index('rank_in_class', 'scores_rank_in_class_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('scores', function (Blueprint $table): void {
            if (Schema::hasColumn('scores', 'assessment_type')) {
                $table->dropIndex('scores_class_subject_assessment_window_index');
                $table->dropIndex('scores_student_assessment_year_index');
                $table->dropIndex('scores_rank_in_class_index');
            }
        });

        Schema::table('scores', function (Blueprint $table): void {
            $dropColumns = [];
            foreach (['assessment_type', 'semester', 'academic_year', 'rank_in_class'] as $column) {
                if (Schema::hasColumn('scores', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
