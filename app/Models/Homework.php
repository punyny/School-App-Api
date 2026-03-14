<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Homework extends Model
{
    protected $table = 'homeworks';

    protected $fillable = [
        'class_id',
        'subject_id',
        'title',
        'question',
        'due_date',
        'due_time',
        'file_attachments',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'file_attachments' => 'array',
        ];
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(HomeworkStatus::class, 'homework_id');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
