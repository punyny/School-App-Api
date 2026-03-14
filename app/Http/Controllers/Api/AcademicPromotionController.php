<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicPromotionController extends Controller
{
    public function promoteClass(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! in_array($user->role, ['super-admin', 'admin'], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $payload = $request->validate([
            'from_class_id' => ['required', 'integer', 'exists:classes,id'],
            'to_class_id' => ['required', 'integer', 'exists:classes,id', 'different:from_class_id'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $fromClass = SchoolClass::query()->findOrFail((int) $payload['from_class_id']);
        $toClass = SchoolClass::query()->findOrFail((int) $payload['to_class_id']);

        if ((int) $fromClass->school_id !== (int) $toClass->school_id) {
            throw ValidationException::withMessages([
                'to_class_id' => ['Source and destination classes must belong to the same school.'],
            ]);
        }

        if ($user->role === 'admin') {
            if (! $user->school_id || (int) $user->school_id !== (int) $fromClass->school_id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        $studentQuery = Student::query()->where('class_id', $fromClass->id);
        if (! empty($payload['student_ids'])) {
            $studentIds = array_values(array_unique(array_map('intval', $payload['student_ids'])));
            $studentQuery->whereIn('id', $studentIds);
        }

        $candidates = $studentQuery->get(['id', 'class_id']);

        if (($payload['dry_run'] ?? false) === true) {
            return response()->json([
                'message' => 'Dry run completed.',
                'data' => [
                    'from_class_id' => $fromClass->id,
                    'to_class_id' => $toClass->id,
                    'students_to_promote' => $candidates->pluck('id')->all(),
                    'total' => $candidates->count(),
                ],
            ]);
        }

        $updated = DB::transaction(function () use ($candidates, $toClass): int {
            $studentIds = $candidates->pluck('id')->all();
            if ($studentIds === []) {
                return 0;
            }

            return Student::query()
                ->whereIn('id', $studentIds)
                ->update(['class_id' => $toClass->id, 'updated_at' => now()]);
        });

        return response()->json([
            'message' => 'Promotion completed.',
            'data' => [
                'from_class_id' => $fromClass->id,
                'to_class_id' => $toClass->id,
                'promoted_count' => $updated,
                'promoted_student_ids' => $candidates->pluck('id')->all(),
            ],
        ]);
    }
}
