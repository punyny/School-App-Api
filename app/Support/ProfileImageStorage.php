<?php

namespace App\Support;

use App\Models\Media;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileImageStorage
{
    public const DEFAULT_MAX_UPLOAD_KB = 10240;

    public static function maxUploadKb(): int
    {
        return max(1, (int) config('uploads.profile_image_max_kb', self::DEFAULT_MAX_UPLOAD_KB));
    }

    public static function maxUploadMb(): string
    {
        $mb = static::maxUploadKb() / 1024;
        $formatted = number_format($mb, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * @return array<int, string>
     */
    public static function uploadValidationRules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimetypes:image/jpeg,image/png,image/webp,image/avif,image/heic,image/heif',
            'mimes:jpg,jpeg,png,webp,avif,heic,heif',
            'max:'.static::maxUploadKb(),
        ];
    }

    public static function acceptAttribute(): string
    {
        return 'image/png,image/jpeg,image/webp,image/avif,image/heic,image/heif,.jpg,.jpeg,.png,.webp,.avif,.heic,.heif';
    }

    /**
     * @return array{
     *     disk:string,
     *     directory:string,
     *     path:string,
     *     url:string,
     *     original_name:string,
     *     mime_type:string|null,
     *     extension:string,
     *     size_bytes:int|null
     * }
     */
    public static function storeWithMetadata(UploadedFile $file, string $directory = 'profiles'): array
    {
        $normalizedDirectory = trim($directory, '/');
        $extension = $file->guessExtension() ?: $file->extension() ?: 'jpg';
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($normalizedDirectory, $filename, 'public');
        $url = Storage::disk('public')->url($path);

        return [
            'disk' => 'public',
            'directory' => $normalizedDirectory,
            'path' => $path,
            'url' => static::normalizePublicUrl($url) ?? $url,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'extension' => $extension,
            'size_bytes' => $file->getSize(),
        ];
    }

    public static function store(UploadedFile $file, string $directory = 'profiles'): string
    {
        return static::storeWithMetadata($file, $directory)['url'];
    }

    public static function normalizePublicUrl(?string $url): ?string
    {
        $value = trim((string) $url);
        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, ['data:'])) {
            return $value;
        }

        $parts = parse_url($value);
        if (! is_array($parts)) {
            return $value;
        }

        $path = (string) ($parts['path'] ?? '');
        if ($path !== '' && Str::startsWith($path, '/storage/')) {
            $query = isset($parts['query']) ? '?'.$parts['query'] : '';
            $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

            return $path.$query.$fragment;
        }

        return $value;
    }

    public static function storeForModel(
        UploadedFile $file,
        Model $mediable,
        ?User $actor = null,
        string $directory = 'profiles',
        string $category = 'profile',
        array $metadata = []
    ): string {
        $stored = static::storeWithMetadata($file, $directory);

        Media::query()
            ->where('mediable_type', $mediable::class)
            ->where('mediable_id', (int) $mediable->getKey())
            ->where('category', $category)
            ->update(['is_primary' => false]);

        Media::query()->create([
            'school_id' => static::resolveSchoolId($mediable, $actor),
            'uploaded_by_user_id' => $actor?->id,
            'mediable_type' => $mediable::class,
            'mediable_id' => (int) $mediable->getKey(),
            'category' => $category,
            'disk' => $stored['disk'],
            'directory' => $stored['directory'],
            'path' => $stored['path'],
            'url' => $stored['url'],
            'original_name' => $stored['original_name'],
            'mime_type' => $stored['mime_type'],
            'extension' => $stored['extension'],
            'size_bytes' => $stored['size_bytes'],
            'is_primary' => true,
            'metadata' => $metadata,
        ]);

        return $stored['url'];
    }

    public static function clearPrimaryForModel(Model $mediable, string $category = 'profile'): void
    {
        Media::query()
            ->where('mediable_type', $mediable::class)
            ->where('mediable_id', (int) $mediable->getKey())
            ->where('category', $category)
            ->update(['is_primary' => false]);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<string, mixed>  $metadata
     * @return array<int, string>
     */
    public static function attachManyToModel(
        array $files,
        Model $mediable,
        ?User $actor = null,
        string $directory = 'attachments',
        string $category = 'attachment',
        array $metadata = []
    ): array {
        $urls = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $urls[] = static::storeForModel($file, $mediable, $actor, $directory, $category, $metadata);
        }

        return $urls;
    }

    private static function resolveSchoolId(Model $mediable, ?User $actor): ?int
    {
        $mediableSchoolId = $mediable->getAttribute('school_id');
        if ($mediableSchoolId !== null && (int) $mediableSchoolId > 0) {
            return (int) $mediableSchoolId;
        }

        $classId = $mediable->getAttribute('class_id');
        if ($classId !== null && (int) $classId > 0) {
            $schoolId = SchoolClass::query()->whereKey((int) $classId)->value('school_id');
            if ($schoolId !== null) {
                return (int) $schoolId;
            }
        }

        $actorSchoolId = $actor?->school_id;
        if ($actorSchoolId !== null && (int) $actorSchoolId > 0) {
            return (int) $actorSchoolId;
        }

        return null;
    }
}
