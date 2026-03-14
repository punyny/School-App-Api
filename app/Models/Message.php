<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'class_id',
        'content',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class, 'message_id');
    }
}
