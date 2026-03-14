<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends Model
{
    protected $table = 'terms';
    protected $primaryKey = 'term_id';

    protected $fillable = [
        'academic_year_id',
        'term_name',
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

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'term_id', 'term_id');
    }
}

