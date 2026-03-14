<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentFee extends Model
{
    protected $table = 'student_fees';
    protected $primaryKey = 'student_fee_id';

    protected $fillable = [
        'enrollment_id',
        'fee_type_id',
        'amount_due',
        'due_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'enrollment_id', 'enrollment_id');
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class, 'fee_type_id', 'fee_type_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'student_fee_id', 'student_fee_id');
    }
}

