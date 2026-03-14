<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    protected $fillable = [
        'student_id',
        'subject_id',
        'class_id',
        'exam_score',
        'total_score',
        'assessment_type',
        'month',
        'semester',
        'academic_year',
        'quarter',
        'period',
        'grade',
        'rank_in_class',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
