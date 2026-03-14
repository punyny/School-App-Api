<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Guardian extends Model
{
    protected $table = 'guardians';
    protected $primaryKey = 'guardian_id';

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'relationship_to_student',
        'phone',
        'email',
        'address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians', 'guardian_id', 'student_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}

