<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'student_code',
        'admission_no',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'phone',
        'email',
        'address',
        'admission_date',
        'status',
        'grade',
        'parent_name',
        'class_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class, 'student_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(Attendance::class, 'student_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'student_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(IncidentReport::class, 'student_id');
    }

    public function homeworkStatuses(): HasMany
    {
        return $this->hasMany(HomeworkStatus::class, 'student_id');
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_child', 'student_id', 'parent_id')
            ->withTimestamps();
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians', 'student_id', 'guardian_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }
}
