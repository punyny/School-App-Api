<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'category' => ['nullable', 'string', 'max:50'],
            'mediable_type' => ['nullable', 'string', 'max:150'],
            'mediable_id' => ['nullable', 'integer'],
            'uploaded_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Media::query()
            ->with(['school', 'uploadedBy'])
            ->orderByDesc('id');

        $this->applyScope($query, $request->user());

        if (isset($filters['school_id']) && $request->user()->role === 'super-admin') {
            $query->where('school_id', (int) $filters['school_id']);
        }

        foreach (['category', 'mediable_type', 'mediable_id', 'uploaded_by_user_id'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function destroy(Request $request, Media $media): JsonResponse
    {
        $this->applyScope(Media::query()->whereKey($media->id), $request->user());

        if (! $this->canAccess($request->user(), $media)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($media->disk !== '' && $media->path !== '' && Storage::disk($media->disk)->exists($media->path)) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $media->delete();

        return response()->json([
            'message' => 'Media deleted successfully.',
        ]);
    }

    private function applyScope(Builder $query, User $user): void
    {
        if ($user->role === 'super-admin') {
            return;
        }

        if ($user->role === 'admin' && $user->school_id) {
            $query->where('school_id', (int) $user->school_id);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function canAccess(User $user, Media $media): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        return $user->role === 'admin'
            && $user->school_id !== null
            && (int) $user->school_id === (int) $media->school_id;
    }
}
