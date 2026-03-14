<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    protected $table = 'enrollments';
    protected $primaryKey = 'enrollment_id';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'class_id',
        'section_id',
        'roll_no',
        'enrollment_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'enrollment_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id', 'section_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class, 'enrollment_id', 'enrollment_id');
    }

    public function studentFees(): HasMany
    {
        return $this->hasMany(StudentFee::class, 'enrollment_id', 'enrollment_id');
    }
}

