<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mark extends Model
{
    protected $table = 'marks';
    protected $primaryKey = 'mark_id';

    protected $fillable = [
        'exam_subject_id',
        'enrollment_id',
        'obtained_marks',
        'grade_letter',
        'remarks',
    ];

    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class, 'exam_subject_id', 'exam_subject_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'enrollment_id', 'enrollment_id');
    }
}

