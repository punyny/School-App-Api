<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $table = 'sections';
    protected $primaryKey = 'section_id';

    protected $fillable = [
        'class_id',
        'academic_year_id',
        'section_name',
        'class_teacher_id',
        'room_no',
    ];

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id');
    }

    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'class_teacher_id', 'teacher_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'section_id', 'section_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'section_id', 'section_id');
    }
}

