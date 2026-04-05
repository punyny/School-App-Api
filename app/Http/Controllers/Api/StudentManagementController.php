<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\PasswordRule;
use App\Support\ProfileImageStorage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentManagementController extends Controller
{
    private ?string $cachedGeneratedPasswordHash = null;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Student::class);

        $filters = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'string', 'max:20'],
            'sort_by' => ['nullable', 'in:id,class_id,grade,created_at,updated_at'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = $this->buildStudentIndexQuery($request->user(), $filters);
        $perPage = $this->resolvePerPage($filters['per_page'] ?? null, $query);

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Student::class);

        $authUser = $request->user();
        $schoolId = $this->resolveSchoolId($authUser, $request);

        $payload = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'student_id' => ['nullable', 'string', 'max:100', Rule::unique('students', 'student_code')],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'khmer_name' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'password' => ['required', 'string', 'max:255', PasswordRule::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image' => ProfileImageStorage::uploadValidationRules(),
            'remove_image' => ['nullable', 'boolean'],
            'grade' => ['nullable', 'string', 'max:20'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_ids' => ['nullable', 'array'],
            'parent_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $uploadedImage = $request->file('image');
        $removeImage = ($payload['remove_image'] ?? false) === true;

        if ($removeImage) {
            $payload['image_url'] = null;
        }
        unset($payload['image'], $payload['remove_image']);

        // Student class is assigned from Class management flow (Create/Edit Class).
        // Ignore class_id during student creation so new students start unassigned.
        unset($payload['class_id']);

        $parentIds = collect($payload['parent_ids'] ?? [])->unique()->values()->all();
        if ($parentIds !== []) {
            $parents = User::query()->whereIn('id', $parentIds)->get(['id', 'role', 'school_id']);
            $invalidParent = $parents->first(fn (User $parent): bool => $parent->role !== 'parent' || (int) $parent->school_id !== $schoolId);

            if ($invalidParent) {
                throw ValidationException::withMessages([
                    'parent_ids' => ['All parent_ids must be parent users from the same school.'],
                ]);
            }
        }

        $studentCode = trim((string) ($payload['student_id'] ?? ''));
        if ($studentCode === '') {
            $studentCode = $this->generateStudentCode((string) ($payload['email'] ?? $payload['name'] ?? 'student'));
            $payload['student_id'] = $studentCode;
        }

        if (! empty($payload['student_id'])) {
            $existingUserCode = User::query()->withTrashed()
                ->where('school_id', $schoolId)
                ->where('user_code', $payload['student_id'])
                ->exists();
            if ($existingUserCode) {
                throw ValidationException::withMessages([
                    'student_id' => ['This student ID already exists in this school.'],
                ]);
            }
        }

        $student = DB::transaction(function () use ($payload, $schoolId, $parentIds): Student {
            $passwordHash = $this->resolvePasswordHash($payload['password'] ?? null);

            $user = User::query()->create([
                'user_code' => $payload['student_id'] ?? null,
                'name' => $payload['name'],
                'first_name' => $payload['first_name'] ?? null,
                'last_name' => $payload['last_name'] ?? null,
                'khmer_name' => $payload['khmer_name'] ?? null,
                'email' => $payload['email'],
                'email_verified_at' => null,
                'role' => 'student',
                'school_id' => $schoolId,
                'phone' => $payload['phone'] ?? null,
                'gender' => $payload['gender'] ?? null,
                'dob' => $payload['dob'] ?? null,
                'password' => $passwordHash,
                'password_hash' => $passwordHash,
                'address' => $payload['address'] ?? null,
                'bio' => $payload['bio'] ?? null,
                'image_url' => $payload['image_url'] ?? null,
                'active' => true,
            ]);

            $student = Student::query()->create([
                'user_id' => $user->id,
                'student_code' => $payload['student_id'] ?? null,
                'grade' => $payload['grade'] ?? null,
                'parent_name' => $payload['parent_name'] ?? null,
                'class_id' => null,
            ]);

            if ($parentIds !== []) {
                $student->parents()->syncWithoutDetaching($parentIds);
            }

            return $student;
        });

        $student->loadMissing('user');
        if ($removeImage && $student->user !== null) {
            ProfileImageStorage::clearPrimaryForModel($student->user);
        } elseif ($uploadedImage !== null && $student->user !== null) {
            $imageUrl = ProfileImageStorage::storeForModel(
                $uploadedImage,
                $student->user,
                $authUser,
                'profiles/students'
            );
            $student->user->forceFill(['image_url' => $imageUrl])->save();
        }

        return response()->json([
            'message' => 'Student created successfully.',
            'data' => $student->load(['user', 'class', 'parents']),
        ], 201);
    }

    public function show(Request $request, Student $student): JsonResponse
    {
        $this->authorize('view', $student);
        $this->ensureStudentAccessible($request->user(), $student);

        return response()->json([
            'data' => $student->load(['user', 'class', 'parents']),
        ]);
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $this->authorize('update', $student);
        $authUser = $request->user();
        $schoolId = $authUser->role === 'super-admin'
            ? (int) ($request->input('school_id') ?? $student->user?->school_id ?? 0)
            : $this->resolveSchoolId($authUser, $request, false);

        $this->ensureStudentAccessible($authUser, $student);

        if ($schoolId <= 0) {
            throw ValidationException::withMessages([
                'school_id' => ['Unable to resolve school for this student update.'],
            ]);
        }

        $payload = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'student_id' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('students', 'student_code')->ignore($student->id),
            ],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'khmer_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:100'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->where(fn ($query) => $query->where('school_id', $schoolId))
                    ->ignore($student->user_id),
            ],
            'password' => ['nullable', 'string', 'max:255', PasswordRule::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image' => ProfileImageStorage::uploadValidationRules(),
            'remove_image' => ['nullable', 'boolean'],
            'grade' => ['nullable', 'string', 'max:20'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_ids' => ['nullable', 'array'],
            'parent_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $uploadedImage = $request->file('image');
        $removeImage = ($payload['remove_image'] ?? false) === true;

        if ($removeImage) {
            $payload['image_url'] = null;
        }
        unset($payload['image'], $payload['remove_image']);

        $targetClassId = array_key_exists('class_id', $payload)
            ? (int) ($payload['class_id'] ?? 0)
            : (int) ($student->class_id ?? 0);
        if ($targetClassId > 0) {
            $class = SchoolClass::query()->findOrFail($targetClassId);
            if ((int) $class->school_id !== $schoolId) {
                throw ValidationException::withMessages([
                    'class_id' => ['Selected class does not belong to your school.'],
                ]);
            }
        }

        $parentIds = collect($payload['parent_ids'] ?? null)->unique()->values()->all();
        if ($parentIds !== []) {
            $parents = User::query()->whereIn('id', $parentIds)->get(['id', 'role', 'school_id']);
            $invalidParent = $parents->first(fn (User $parent): bool => $parent->role !== 'parent' || (int) $parent->school_id !== $schoolId);

            if ($invalidParent) {
                throw ValidationException::withMessages([
                    'parent_ids' => ['All parent_ids must be parent users from the same school.'],
                ]);
            }
        }

        if (isset($payload['student_id'])) {
            $existingUserCode = User::query()->withTrashed()
                ->where('school_id', $schoolId)
                ->where('user_code', $payload['student_id'])
                ->whereKeyNot($student->user_id)
                ->exists();
            if ($existingUserCode) {
                throw ValidationException::withMessages([
                    'student_id' => ['This student ID already exists in this school.'],
                ]);
            }
        }

        $emailChanged = array_key_exists('email', $payload)
            && trim((string) $payload['email']) !== ''
            && ! hash_equals(
                mb_strtolower((string) ($student->user?->email ?? '')),
                mb_strtolower(trim((string) $payload['email']))
            );

        DB::transaction(function () use ($student, $payload, $targetClassId, $parentIds, $emailChanged): void {
            $userUpdates = [];
            foreach (['name', 'first_name', 'last_name', 'khmer_name', 'email', 'phone', 'gender', 'dob', 'address', 'bio', 'image_url'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $userUpdates[$field] = $payload[$field];
                }
            }
            if ($emailChanged) {
                $userUpdates['email_verified_at'] = null;
            }
            if (array_key_exists('student_id', $payload)) {
                $userUpdates['user_code'] = $payload['student_id'];
            }
            if ($userUpdates !== []) {
                $student->user()->update($userUpdates);
            }

            if (! empty($payload['password'])) {
                $passwordHash = Hash::make($payload['password']);
                $student->user()->update([
                    'password' => $passwordHash,
                    'password_hash' => $passwordHash,
                ]);
            }

            $studentUpdates = [];

            if (array_key_exists('class_id', $payload)) {
                $studentUpdates['class_id'] = $payload['class_id'] ? (int) $payload['class_id'] : null;
            }

            foreach (['student_id' => 'student_code', 'grade' => 'grade', 'parent_name' => 'parent_name'] as $input => $column) {
                if (array_key_exists($input, $payload)) {
                    $studentUpdates[$column] = $payload[$input];
                }
            }

            if ($studentUpdates !== []) {
                $student->update($studentUpdates);
            } elseif (! array_key_exists('class_id', $payload) && $targetClassId > 0) {
                $student->update(['class_id' => $targetClassId]);
            }

            if (array_key_exists('parent_ids', $payload)) {
                $student->parents()->sync($parentIds);
            }
        });

        $student->loadMissing('user');
        if ($removeImage && $student->user !== null) {
            ProfileImageStorage::clearPrimaryForModel($student->user);
        } elseif ($uploadedImage !== null && $student->user !== null) {
            $imageUrl = ProfileImageStorage::storeForModel(
                $uploadedImage,
                $student->user,
                $authUser,
                'profiles/students'
            );
            $student->user->forceFill(['image_url' => $imageUrl])->save();
        }

        return response()->json([
            'message' => 'Student updated successfully.',
            'data' => $student->fresh()->load(['user', 'class', 'parents']),
        ]);
    }

    public function destroy(Request $request, Student $student): JsonResponse
    {
        $this->authorize('delete', $student);
        $this->ensureStudentAccessible($request->user(), $student);

        DB::transaction(function () use ($student): void {
            $studentUser = $student->user;
            if ($studentUser) {
                $studentUser->delete();
            }
            $student->delete();
        });

        return response()->json([
            'message' => 'Student deleted successfully.',
        ]);
    }

    public function restore(Request $request, int $studentId): JsonResponse
    {
        $authUser = $request->user();
        if (! in_array($authUser->role, ['super-admin', 'admin'], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $student = Student::query()->withTrashed()->findOrFail($studentId);
        $studentUser = User::query()->withTrashed()->find($student->user_id);

        if ($authUser->role === 'admin') {
            if (! $authUser->school_id || ! $studentUser || (int) $studentUser->school_id !== (int) $authUser->school_id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        DB::transaction(function () use ($student, $studentUser): void {
            if ($studentUser && method_exists($studentUser, 'trashed') && $studentUser->trashed()) {
                $studentUser->restore();
            }

            if (method_exists($student, 'trashed') && $student->trashed()) {
                $student->restore();
            }
        });

        return response()->json([
            'message' => 'Student restored successfully.',
            'data' => $student->fresh()->load(['user', 'class', 'parents']),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Student::class);

        $filters = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'in:id,class_id,grade,created_at,updated_at'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = $this->buildStudentIndexQuery($request->user(), $filters);
        $rows = $query->get();
        $fileName = 'students_export_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            if (! $handle) {
                return;
            }

            fputcsv($handle, [
                'student_id',
                'student_code',
                'user_id',
                'user_code',
                'name',
                'khmer_name',
                'first_name',
                'last_name',
                'email',
                'phone',
                'class_id',
                'class_name',
                'grade',
                'parent_name',
                'active',
                'parent_ids',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->student_code,
                    $row->user_id,
                    $row->user?->user_code,
                    $row->user?->name,
                    $row->user?->khmer_name,
                    $row->user?->first_name,
                    $row->user?->last_name,
                    $row->user?->email,
                    $row->user?->phone,
                    $row->class_id,
                    $row->class?->name,
                    $row->grade,
                    $row->parent_name,
                    $row->user?->active ? '1' : '0',
                    implode('|', $row->parents->pluck('id')->all()),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importCsv(Request $request): JsonResponse
    {
        $this->authorize('create', Student::class);
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        @ini_set('max_execution_time', '120');

        $payload = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
        ]);

        $authUser = $request->user();
        $defaultSchoolId = $authUser->role === 'super-admin'
            ? (int) ($payload['school_id'] ?? 0)
            : (int) ($authUser->school_id ?? 0);

        if ($defaultSchoolId <= 0 && $authUser->role !== 'super-admin') {
            throw ValidationException::withMessages([
                'school_id' => ['This admin account has no school assigned.'],
            ]);
        }

        $lines = file($payload['file']->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($lines) || count($lines) < 2) {
            throw ValidationException::withMessages([
                'file' => ['CSV must include a header row and at least one data row.'],
            ]);
        }

        $header = array_map(fn (string $item): string => trim($item), str_getcsv((string) array_shift($lines)));
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($lines as $lineNumber => $line) {
            $cells = str_getcsv((string) $line);
            $row = [];
            foreach ($header as $index => $column) {
                $row[$column] = isset($cells[$index]) ? trim((string) $cells[$index]) : null;
            }

            try {
                $rowSchoolId = $defaultSchoolId;
                if ($authUser->role === 'super-admin') {
                    $rawSchoolId = $this->csvValue($row, ['school_id', 'school id', 'school']);
                    if ($rawSchoolId !== '') {
                        $rowSchoolId = (int) $rawSchoolId;
                    }
                }

                if ($rowSchoolId <= 0) {
                    throw ValidationException::withMessages([
                        'school_id' => ['school_id is required for super-admin imports.'],
                    ]);
                }

                $firstName = $this->csvValue($row, ['first_name', 'first name', 'fist_name', 'fist name']);
                $lastName = $this->csvValue($row, ['last_name', 'last name']);
                $khmerName = $this->csvValue($row, ['khmer_name', 'khmer name', 'name_kh', 'kh_name']);
                $phone = $this->csvValue($row, ['phone', 'phone_number', 'phone number']);
                $email = $this->csvValue($row, ['email', 'e-mail']);
                $studentCode = $this->csvValue($row, ['student_id', 'student id', 'id']);

                if ($email === '') {
                    throw ValidationException::withMessages([
                        'email' => ['email is required.'],
                    ]);
                }

                $name = $this->csvValue($row, ['name', 'full_name', 'full name']);

                if ($name === '') {
                    $name = trim($firstName.' '.$lastName);
                }

                if ($name === '') {
                    $name = $khmerName;
                }

                if ($name === '') {
                    throw ValidationException::withMessages([
                        'name' => ['name is required for each CSV row.'],
                    ]);
                }

                if ($khmerName === '') {
                    $khmerName = $name;
                }

                if ($studentCode === '') {
                    $studentCode = $this->generateStudentCode($email !== '' ? $email : $name);
                }

                $classId = $this->resolveClassIdFromImportRow($row, $rowSchoolId, false);
                $gender = $this->normalizeGenderFromCsv($this->csvValue($row, ['gender', 'sex']));
                $dob = $this->normalizeDobFromCsv($this->csvValue($row, ['dob', 'date_of_birth', 'date of birth', 'birth_date', 'birth date']));
                $grade = $this->csvValue($row, ['grade', 'grade_level', 'grade level']);
                $parentName = $this->csvValue($row, ['parent_name', 'parent name', 'guardian_name', 'guardian name']);
                $address = $this->csvValue($row, ['address']);
                $bio = $this->csvValue($row, ['bio']);
                $imageUrl = $this->csvValue($row, ['image_url', 'image url', 'avatar']);
                $password = $this->csvValue($row, ['password']);
                $hasPassword = $password !== '';
                $passwordHash = $hasPassword ? Hash::make($password) : null;

                DB::transaction(function () use (
                    $rowSchoolId,
                    $classId,
                    $studentCode,
                    $khmerName,
                    $firstName,
                    $lastName,
                    $name,
                    $email,
                    $phone,
                    $gender,
                    $dob,
                    $grade,
                    $parentName,
                    $address,
                    $bio,
                    $imageUrl,
                    $hasPassword,
                    $passwordHash,
                    &$created,
                    &$updated
                ): void {
                    $user = User::query()->withTrashed()
                        ->where('school_id', $rowSchoolId)
                        ->where('user_code', $studentCode)
                        ->first();

                    if (! $user) {
                        $user = User::query()->withTrashed()
                            ->where('school_id', $rowSchoolId)
                            ->where('email', $email)
                            ->first();
                    }

                    $userCodeConflict = User::query()->withTrashed()
                        ->where('school_id', $rowSchoolId)
                        ->where('user_code', $studentCode)
                        ->when($user !== null, fn (Builder $query) => $query->whereKeyNot($user->id))
                        ->exists();
                    if ($userCodeConflict) {
                        throw ValidationException::withMessages([
                            'student_id' => ["student_id '{$studentCode}' already exists in school {$rowSchoolId}."],
                        ]);
                    }

                    $emailConflict = User::query()->withTrashed()
                        ->where('school_id', $rowSchoolId)
                        ->where('email', $email)
                        ->when($user !== null, fn (Builder $query) => $query->whereKeyNot($user->id))
                        ->exists();
                    if ($emailConflict) {
                        throw ValidationException::withMessages([
                            'email' => ["email '{$email}' already exists in school {$rowSchoolId}."],
                        ]);
                    }

                    $wasCreated = false;
                    if (! $user) {
                        $wasCreated = true;
                        $storedPasswordHash = $passwordHash ?? $this->resolvePasswordHash(null);
                        $user = User::query()->create([
                            'role' => 'student',
                            'user_code' => $studentCode,
                            'first_name' => $firstName !== '' ? $firstName : null,
                            'last_name' => $lastName !== '' ? $lastName : null,
                            'khmer_name' => $khmerName,
                            'name' => $name,
                            'email' => $email,
                            'phone' => $phone !== '' ? $phone : null,
                            'gender' => $gender,
                            'dob' => $dob,
                            'address' => $address !== '' ? $address : null,
                            'bio' => $bio !== '' ? $bio : null,
                            'image_url' => $imageUrl !== '' ? $imageUrl : null,
                            'password' => $storedPasswordHash,
                            'password_hash' => $storedPasswordHash,
                            'school_id' => $rowSchoolId,
                            'active' => true,
                        ]);
                    } else {
                        if ($user->trashed()) {
                            $user->restore();
                        }

                        if ($user->role !== 'student') {
                            throw ValidationException::withMessages([
                                'email' => ["User '{$email}' exists but role is '{$user->role}', not student."],
                            ]);
                        }

                        $updates = [
                            'user_code' => $studentCode,
                            'first_name' => $firstName !== '' ? $firstName : $user->first_name,
                            'last_name' => $lastName !== '' ? $lastName : $user->last_name,
                            'khmer_name' => $khmerName,
                            'name' => $name,
                            'email' => $email,
                            'phone' => $phone !== '' ? $phone : $user->phone,
                            'gender' => $gender ?? $user->gender,
                            'dob' => $dob ?? $user->dob,
                            'address' => $address !== '' ? $address : $user->address,
                            'bio' => $bio !== '' ? $bio : $user->bio,
                            'image_url' => $imageUrl !== '' ? $imageUrl : $user->image_url,
                            'school_id' => $rowSchoolId,
                        ];

                        if ($hasPassword && $passwordHash !== null) {
                            $updates['password'] = $passwordHash;
                            $updates['password_hash'] = $passwordHash;
                        }

                        $user->fill($updates)->save();
                    }

                    $studentCodeConflict = Student::query()->withTrashed()
                        ->where('student_code', $studentCode)
                        ->where('user_id', '!=', $user->id)
                        ->exists();
                    if ($studentCodeConflict) {
                        throw ValidationException::withMessages([
                            'student_id' => ["student_id '{$studentCode}' already exists."],
                        ]);
                    }

                    $student = Student::query()->withTrashed()->firstOrNew(['user_id' => $user->id]);
                    if ($student->exists && method_exists($student, 'trashed') && $student->trashed()) {
                        $student->restore();
                    }

                    $student->fill([
                        'student_code' => $studentCode,
                        'class_id' => $classId,
                        'grade' => $grade !== '' ? $grade : $student->grade,
                        'parent_name' => $parentName !== '' ? $parentName : $student->parent_name,
                    ])->save();

                    if ($wasCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }
                });
            } catch (\Throwable $exception) {
                $message = $exception instanceof ValidationException
                    ? (collect($exception->errors())->flatten()->first() ?? $exception->getMessage())
                    : $exception->getMessage();

                $errors[] = [
                    'line' => $lineNumber + 2,
                    'message' => $message,
                ];
            }
        }

        return response()->json([
            'message' => 'Student CSV import completed.',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors,
            ],
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveClassIdFromImportRow(array $row, int $schoolId, bool $required = true): ?int
    {
        $rawClass = $this->csvValue($row, ['class_id', 'class id', 'class_name', 'class name', 'class']);
        if ($rawClass === '') {
            if (! $required) {
                return null;
            }

            throw ValidationException::withMessages([
                'class' => ['class (or class_id) is required for student import row.'],
            ]);
        }

        if (ctype_digit($rawClass)) {
            $classId = (int) $rawClass;
            $class = SchoolClass::query()->find($classId);
            if (! $class || (int) $class->school_id !== $schoolId) {
                throw ValidationException::withMessages([
                    'class_id' => ['Selected class does not belong to the selected school.'],
                ]);
            }

            return $classId;
        }

        $className = trim((string) Str::of($rawClass)->before('('));
        $class = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where(function (Builder $query) use ($rawClass, $className): void {
                $query->whereRaw('LOWER(name) = ?', [Str::lower($rawClass)]);
                if ($className !== '') {
                    $query->orWhereRaw('LOWER(name) = ?', [Str::lower($className)]);
                }
            })
            ->first();

        if (! $class) {
            $normalizedRaw = $this->normalizeCsvKey($rawClass);
            $classes = SchoolClass::query()
                ->where('school_id', $schoolId)
                ->get(['id', 'name', 'grade_level']);

            $class = $classes->first(function (SchoolClass $item) use ($normalizedRaw): bool {
                $name = trim((string) $item->name);
                $grade = trim((string) ($item->grade_level ?? ''));
                $candidates = [
                    $name,
                    trim($name.' '.$grade),
                    trim($grade.' '.$name),
                    trim($name.'('.$grade.')'),
                    trim($grade.'('.$name.')'),
                ];

                foreach ($candidates as $candidate) {
                    $normalizedCandidate = $this->normalizeCsvKey($candidate);
                    if ($normalizedCandidate === '') {
                        continue;
                    }

                    if (
                        $normalizedRaw === $normalizedCandidate
                        || Str::contains($normalizedRaw, $normalizedCandidate)
                        || Str::contains($normalizedCandidate, $normalizedRaw)
                    ) {
                        return true;
                    }
                }

                return false;
            });
        }

        if (! $class) {
            throw ValidationException::withMessages([
                'class' => ["Class '{$rawClass}' was not found in school {$schoolId}."],
            ]);
        }

        return (int) $class->id;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function csvValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                $value = trim((string) ($row[$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        $normalizedMap = [];
        foreach ($row as $column => $value) {
            $normalized = $this->normalizeCsvKey((string) $column);
            if ($normalized === '') {
                continue;
            }
            $normalizedMap[$normalized] = trim((string) ($value ?? ''));
        }

        foreach ($keys as $key) {
            $normalizedKey = $this->normalizeCsvKey($key);
            if ($normalizedKey === '') {
                continue;
            }

            $value = $normalizedMap[$normalizedKey] ?? '';
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function normalizeCsvKey(string $key): string
    {
        $normalized = Str::lower(trim($key));
        $normalized = (string) preg_replace('/[^a-z0-9]+/', '', $normalized);

        return $normalized;
    }

    private function normalizeDobFromCsv(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'dob' => ["Invalid dob value '{$value}'. Use YYYY-MM-DD format."],
            ]);
        }
    }

    private function normalizeGenderFromCsv(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $normalized = Str::lower($value);

        return match ($normalized) {
            'm', 'male' => 'male',
            'f', 'female' => 'female',
            'other' => 'other',
            default => throw ValidationException::withMessages([
                'gender' => ["Invalid gender '{$value}'. Allowed: male, female, other."],
            ]),
        };
    }

    private function generatePlaceholderEmail(string $seed, int $schoolId): string
    {
        $base = Str::slug($seed, '.');
        if ($base === '') {
            $base = 'student';
        }

        $domain = 'school'.$schoolId.'.local';
        for ($i = 0; $i < 500; $i++) {
            $local = $i === 0 ? $base : $base.$i;
            $email = $local.'@'.$domain;

            $exists = User::query()->withTrashed()
                ->where('school_id', $schoolId)
                ->where('email', $email)
                ->exists();

            if (! $exists) {
                return $email;
            }
        }

        return Str::uuid()->toString().'@'.$domain;
    }

    private function generateStudentCode(string $seed): string
    {
        $base = Str::upper(Str::slug($seed, ''));
        if ($base === '') {
            $base = 'STU';
        }
        $base = substr($base, 0, 12);

        for ($i = 0; $i < 500; $i++) {
            $code = $i === 0 ? $base : $base.$i;
            $exists = Student::query()->withTrashed()
                ->where('student_code', $code)
                ->exists();
            if (! $exists) {
                return $code;
            }
        }

        return 'STU'.strtoupper(substr((string) Str::uuid(), 0, 10));
    }

    private function resolveSchoolId(User $authUser, Request $request, bool $requirePayloadForSuperAdmin = true): int
    {
        if ($authUser->role === 'super-admin') {
            $rule = $requirePayloadForSuperAdmin ? 'required' : 'nullable';
            $schoolPayload = $request->validate([
                'school_id' => [$rule, 'integer', 'exists:schools,id'],
            ]);

            if (isset($schoolPayload['school_id'])) {
                return (int) $schoolPayload['school_id'];
            }

            return (int) ($authUser->school_id ?? 0);
        }

        if ($authUser->role !== 'admin') {
            throw ValidationException::withMessages([
                'role' => ['Only admin or super-admin can create students.'],
            ]);
        }

        if (! $authUser->school_id) {
            throw ValidationException::withMessages([
                'school_id' => ['This admin account has no school assigned.'],
            ]);
        }

        return (int) $authUser->school_id;
    }

    private function ensureStudentAccessible(User $authUser, Student $student): void
    {
        if ($authUser->role === 'super-admin') {
            return;
        }

        if ($authUser->role !== 'admin' || ! $authUser->school_id) {
            throw ValidationException::withMessages([
                'role' => ['Only admin or super-admin can manage students.'],
            ]);
        }

        $studentSchoolId = (int) ($student->user?->school_id ?? 0);
        if ($studentSchoolId !== (int) $authUser->school_id) {
            throw ValidationException::withMessages([
                'student_id' => ['Student does not belong to your school.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildStudentIndexQuery(User $authUser, array $filters): Builder
    {
        $schoolId = null;
        if ($authUser->role === 'admin') {
            if (! $authUser->school_id) {
                throw ValidationException::withMessages([
                    'school_id' => ['This admin account has no school assigned.'],
                ]);
            }
            $schoolId = (int) ($authUser->school_id ?? 0);
        } elseif ($authUser->role === 'super-admin') {
            $schoolId = isset($filters['school_id']) ? (int) $filters['school_id'] : null;
        }

        $query = Student::query()
            ->with(['user', 'class', 'parents'])
            ->whereHas('user', fn (Builder $userQuery) => $userQuery->where('role', 'student'))
            ->orderBy(
                $filters['sort_by'] ?? 'id',
                $filters['sort_dir'] ?? 'desc'
            );

        if ($schoolId) {
            $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where('school_id', $schoolId));
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->whereHas('user', function (Builder $userQuery) use ($search): void {
                $userQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('active', $filters)) {
            $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where('active', $filters['active']));
        }

        return $query;
    }

    private function resolvePerPage(mixed $perPageInput, Builder $query): int
    {
        $raw = trim((string) ($perPageInput ?? ''));
        if ($raw === '') {
            return 20;
        }

        if (Str::lower($raw) === 'all') {
            $total = (clone $query)->toBase()->getCountForPagination();

            return max(1, min($total, 5000));
        }

        if (! ctype_digit($raw)) {
            throw ValidationException::withMessages([
                'per_page' => ['per_page must be a number or all.'],
            ]);
        }

        $value = (int) $raw;
        if ($value < 1 || $value > 5000) {
            throw ValidationException::withMessages([
                'per_page' => ['per_page must be between 1 and 5000, or all.'],
            ]);
        }

        return $value;
    }

    private function resolvePasswordHash(mixed $password): string
    {
        $plain = trim((string) ($password ?? ''));
        if ($plain === '') {
            if ($this->cachedGeneratedPasswordHash === null) {
                $this->cachedGeneratedPasswordHash = Hash::make(Str::random(40));
            }

            return $this->cachedGeneratedPasswordHash;
        }

        return Hash::make($plain);
    }
}
