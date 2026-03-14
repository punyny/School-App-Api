<?php

namespace App\Models;

use App\Support\ProfileImageStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'username',
        'role',
        'role_id',
        'user_code',
        'first_name',
        'last_name',
        'khmer_name',
        'name',
        'email',
        'phone',
        'gender',
        'dob',
        'password',
        'password_hash',
        'address',
        'bio',
        'image_url',
        'school_id',
        'active',
        'is_active',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'is_active' => 'boolean',
            'last_login' => 'datetime',
            'dob' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->isDirty('active') && ! $user->isDirty('is_active')) {
                $user->is_active = (bool) $user->active;
            }

            if ($user->isDirty('is_active') && ! $user->isDirty('active')) {
                $user->active = (bool) $user->is_active;
            }

            if (($user->username === null || trim($user->username) === '')) {
                $base = '';
                if (is_string($user->email) && $user->email !== '') {
                    $base = Str::before($user->email, '@');
                } elseif (is_string($user->name) && $user->name !== '') {
                    $base = Str::slug($user->name, '_');
                }

                $base = preg_replace('/[^a-z0-9_]+/i', '_', strtolower((string) $base));
                $base = trim((string) $base, '_');
                if ($base === '') {
                    $base = 'user';
                }

                $candidate = mb_substr($base, 0, 35);
                $counter = 0;
                do {
                    $suffix = $counter === 0 ? '' : '_'.$counter;
                    $username = mb_substr($candidate, 0, 50 - mb_strlen($suffix)).$suffix;
                    $exists = static::query()
                        ->where('username', $username)
                        ->when($user->exists, fn ($query) => $query->whereKeyNot($user->getKey()))
                        ->exists();
                    $counter++;
                } while ($exists);

                $user->username = $username;
            }

            if (($user->isDirty('role') || $user->role_id === null) && Schema::hasTable('roles')) {
                $roleName = match ((string) $user->role) {
                    'super-admin' => 'Super Admin',
                    'admin' => 'Admin',
                    'teacher' => 'Teacher',
                    'student' => 'Student',
                    'parent', 'guardian' => 'Guardian',
                    default => null,
                };

                if ($roleName !== null) {
                    $user->role_id = Role::query()->where('role_name', $roleName)->value('role_id') ?? $user->role_id;
                }
            }
        });
    }

    public function getRoleAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::normalizeRoleValue($value);
    }

    public function setRoleAttribute(?string $value): void
    {
        if ($value === null) {
            $this->attributes['role'] = null;

            return;
        }

        $this->attributes['role'] = self::normalizeRoleValue($value);
    }

    public function getImageUrlAttribute(?string $value): ?string
    {
        return ProfileImageStorage::normalizePublicUrl($value);
    }

    public function setImageUrlAttribute(?string $value): void
    {
        $this->attributes['image_url'] = ProfileImageStorage::normalizePublicUrl($value);
    }

    public function normalizedRole(): string
    {
        return self::normalizeRoleValue((string) ($this->role ?? ''));
    }

    private static function normalizeRoleValue(string $role): string
    {
        $value = Str::lower(trim($role));

        return match ($value) {
            'super admin', 'super_admin', 'super-admin' => 'super-admin',
            'guardian', 'parent' => 'parent',
            default => $value,
        };
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function roleRef(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(Student::class, 'user_id');
    }

    public function teachingClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'teacher_class', 'teacher_id', 'class_id')
            ->withPivot('subject_id')
            ->withTimestamps();
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_child', 'parent_id', 'student_id')
            ->withTimestamps();
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(Teacher::class, 'user_id');
    }

    public function guardianProfile(): HasOne
    {
        return $this->hasOne(Guardian::class, 'user_id');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
