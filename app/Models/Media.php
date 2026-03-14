<?php

namespace App\Models;

use App\Support\ProfileImageStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    protected $fillable = [
        'school_id',
        'uploaded_by_user_id',
        'mediable_type',
        'mediable_id',
        'category',
        'disk',
        'directory',
        'path',
        'url',
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
        'is_primary',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'size_bytes' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function getUrlAttribute(?string $value): ?string
    {
        return ProfileImageStorage::normalizePublicUrl($value);
    }

    public function setUrlAttribute(?string $value): void
    {
        $this->attributes['url'] = ProfileImageStorage::normalizePublicUrl($value);
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
