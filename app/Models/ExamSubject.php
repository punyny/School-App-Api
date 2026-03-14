<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSubject extends Model
{
    protected $table = 'exam_subjects';
    protected $primaryKey = 'exam_subject_id';

    protected $fillable = [
        'exam_id',
        'subject_id',
        'max_marks',
        'pass_marks',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'exam_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class, 'exam_subject_id', 'exam_subject_id');
    }
}

