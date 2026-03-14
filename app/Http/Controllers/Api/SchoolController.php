<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', School::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = School::query()
            ->withCount(['users', 'classes', 'subjects'])
            ->orderBy('name');

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($scope) use ($search): void {
                $scope->where('name', 'like', "%{$search}%")
                    ->orWhere('school_code', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(School $school): JsonResponse
    {
        $this->authorize('view', $school);

        return response()->json([
            'data' => $school->loadCount(['users', 'classes', 'subjects']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', School::class);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('schools', 'name')],
            'school_code' => ['nullable', 'string', 'max:100', Rule::unique('schools', 'school_code')],
            'location' => ['nullable', 'string', 'max:255'],
            'config_details' => ['nullable'],
        ]);
        $payload['config_details'] = $this->normalizeConfigDetails($payload['config_details'] ?? null);

        $school = School::query()->create($payload);

        return response()->json([
            'message' => 'School created successfully.',
            'data' => $school,
        ], 201);
    }

    public function update(Request $request, School $school): JsonResponse
    {
        $this->authorize('update', $school);

        $payload = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('schools', 'name')->ignore($school->id)],
            'school_code' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('schools', 'school_code')->ignore($school->id)],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'config_details' => ['sometimes', 'nullable'],
        ]);
        if (array_key_exists('config_details', $payload)) {
            $payload['config_details'] = $this->normalizeConfigDetails($payload['config_details']);
        }

        $school->fill($payload)->save();

        return response()->json([
            'message' => 'School updated successfully.',
            'data' => $school->fresh(),
        ]);
    }

    public function destroy(School $school): JsonResponse
    {
        $this->authorize('delete', $school);

        $school->delete();

        return response()->json([
            'message' => 'School deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeConfigDetails(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $rawValue = trim((string) $value);
        if ($rawValue === '') {
            return [];
        }

        $decoded = json_decode($rawValue, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return [
            'notes' => $rawValue,
        ];
    }
}
