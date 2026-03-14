<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_id',
        'school_id',
        'actor_name',
        'actor_role',
        'method',
        'endpoint',
        'action',
        'resource_type',
        'resource_id',
        'ip_address',
        'user_agent',
        'request_payload',
        'status_code',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }
}

