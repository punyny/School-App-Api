<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LeaveRequest extends Model
{
    protected $table = 'leaverequest';

    protected $fillable = [
        'student_id',
        'subject_id',
        'request_type',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'return_date',
        'total_days',
        'subject_ids',
        'reason',
        'status',
        'submitted_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'return_date' => 'date',
            'subject_ids' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'leave_request_recipients', 'leave_request_id', 'user_id')
            ->withPivot('recipient_role')
            ->withTimestamps();
    }
}
