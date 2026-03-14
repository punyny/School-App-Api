<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    protected $table = 'teachers';
    protected $primaryKey = 'teacher_id';

    protected $fillable = [
        'user_id',
        'employee_no',
        'first_name',
        'last_name',
        'phone',
        'email',
        'specialization',
        'hire_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'teacher_id', 'teacher_id');
    }
}

