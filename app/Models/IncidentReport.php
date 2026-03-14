<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentReport extends Model
{
    protected $table = 'incidentreport';

    protected $fillable = [
        'student_id',
        'description',
        'date',
        'type',
        'acknowledged',
        'reporter_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'acknowledged' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
