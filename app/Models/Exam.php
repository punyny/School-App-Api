<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $table = 'exams';
    protected $primaryKey = 'exam_id';

    protected $fillable = [
        'term_id',
        'exam_name',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id', 'term_id');
    }

    public function examSubjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class, 'exam_id', 'exam_id');
    }
}

