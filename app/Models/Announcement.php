<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'message',
        'content',
        'file_attachments',
        'audience',
        'date',
        'school_id',
        'class_id',
        'target_role',
        'target_user_id',
        'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'file_attachments' => 'array',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
