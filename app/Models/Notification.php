<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'notification';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'date',
        'read_status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'read_status' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
