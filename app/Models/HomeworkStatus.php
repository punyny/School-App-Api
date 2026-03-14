<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkStatus extends Model
{
    protected $table = 'homeworkstatus';

    protected $fillable = [
        'homework_id',
        'student_id',
        'status',
        'completion_date',
    ];

    protected function casts(): array
    {
        return [
            'completion_date' => 'date',
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
}
