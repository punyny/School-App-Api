<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeType extends Model
{
    protected $table = 'fee_types';
    protected $primaryKey = 'fee_type_id';

    protected $fillable = [
        'fee_name',
        'description',
        'default_amount',
    ];

    public function studentFees(): HasMany
    {
        return $this->hasMany(StudentFee::class, 'fee_type_id', 'fee_type_id');
    }
}

