<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $fillable = [
        'subject_code',
        'subject_name',
        'is_optional',
        'name',
        'full_score',
        'school_id',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function timetables(): HasMany
    {
        return $this->hasMany(Timetable::class, 'subject_id');
    }

    public function homeworks(): HasMany
    {
        return $this->hasMany(Homework::class, 'subject_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class, 'subject_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'subject_id');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'subject_id');
    }

    public function examSubjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class, 'subject_id');
    }
}
