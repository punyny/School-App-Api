<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class HomeworkSubmission extends Model
{
    protected $fillable = [
        'homework_id',
        'student_id',
        'answer_text',
        'file_attachments',
        'submitted_at',
        'teacher_score',
        'teacher_score_max',
        'score_weight_percent',
        'score_assessment_type',
        'score_month',
        'score_semester',
        'score_academic_year',
        'teacher_feedback',
        'graded_at',
        'graded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'file_attachments' => 'array',
            'submitted_at' => 'datetime',
            'teacher_score' => 'decimal:2',
            'teacher_score_max' => 'decimal:2',
            'score_weight_percent' => 'decimal:2',
            'score_month' => 'integer',
            'score_semester' => 'integer',
            'graded_at' => 'datetime',
        ];
    }

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class, 'homework_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by_user_id');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
