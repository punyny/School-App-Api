<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    protected $table = 'academic_years';
    protected $primaryKey = 'academic_year_id';

    protected $fillable = [
        'year_name',
        'start_date',
        'end_date',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class, 'academic_year_id', 'academic_year_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'academic_year_id', 'academic_year_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'academic_year_id', 'academic_year_id');
    }
}

