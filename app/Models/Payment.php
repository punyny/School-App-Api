<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'payments';
    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'student_fee_id',
        'amount_paid',
        'payment_date',
        'payment_method',
        'reference_no',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
        ];
    }

    public function studentFee(): BelongsTo
    {
        return $this->belongsTo(StudentFee::class, 'student_fee_id', 'student_fee_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}

