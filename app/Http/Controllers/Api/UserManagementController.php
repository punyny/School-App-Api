<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class UserManagementController extends Controller
{
    private ?string $cachedGeneratedPasswordHash = null;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $authUser = $request->user();
        $authRole = $this->normalizedRole($authUser);
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'role' => ['nullable', 'in:super-admin,admin,teacher,student,parent,guardian'],
            'active' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'string', 'max:20'],
        ]);

        $query = User::query()
            ->with(['school', 'studentProfile.class'])
            ->orderByDesc('id');

        $this->applyVisibilityScope($query, $authUser);
        $this->applyIndexFilters($query, $filters, $authRole);
        $perPage = $this->resolvePerPage($filters['per_page'] ?? null, $query);

        return response()->json($query->paginate($perPage));
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $this->ensureUserAccessible($request->user(), $user);

        return response()->json([
            'data' => $user->load($this->userDetailRelations()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $this->normalizeIncomingUserAliases($request, true);

        $authUser = $request->user();
        $payload = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'school_code' => ['nullable', 'string', 'max:100'],
            'school_name' => ['nullable', 'string', 'max:100'],
            'school_camp' => ['nullable', 'string', 'max:255'],
            'school_location' => ['nullable', 'string', 'max:255'],
            'role' => ['required', 'in:super-admin,admin,teacher,student,parent,guardian'],
            'username' => ['nullable', 'string', 'max:50'],
            'user_code' => ['nullable', 'string', 'max:100'],
            'admin_id' => ['nullable', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'khmer_name' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:100'],
            'admin_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255', PasswordRule::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image' => ProfileImageStorage::uploadValidationRules(),
            'remove_image' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'student_id' => ['nullable', 'string', 'max:100'],
            'grade' => ['nullable', 'string', 'max:20'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_ids' => ['nullable', 'array'],
            'parent_ids.*' => ['integer', 'exists:users,id'],
            'child_ids' => ['nullable', 'array'],
            'child_ids.*' => ['integer', 'exists:students,id'],
        ]);

        $uploadedImage = $request->file('image');
        $removeImage = ($payload['remove_image'] ?? false) === true;

        if ($removeImage) {
            $payload['image_url'] = null;
        }
        unset($payload['image'], $payload['remove_image']);

        $targetRole = $this->normalizeRoleInput((string) $payload['role']);
        $this->ensureAssignableRole($authUser, $targetRole, true);
        $authRole = $this->normalizedRole($authUser);

        $schoolIdProvided = array_key_exists('school_id', $payload) && $payload['school_id'] !== null;
        $schoolCode = trim((string) ($payload['school_code'] ?? ''));
        $schoolName = trim((string) ($payload['school_name'] ?? ''));
        $schoolCamp = trim((string) ($payload['school_camp'] ?? $payload['school_location'] ?? ''));
        $requestedSchoolId = $payload['school_id'] ?? null;
        if ($requestedSchoolId === null && $authRole === 'super-admin') {
            if ($targetRole === 'admin' && ($schoolName !== '' || $schoolCode !== '')) {
                $requestedSchoolId = $this->resolveOrCreateSchoolIdForAdmin($schoolName, $schoolCamp, $schoolCode);
            } elseif ($schoolCode !== '') {
                $requestedSchoolId = $this->resolveSchoolIdByCode($schoolCode);
            } elseif ($schoolName !== '') {
                $requestedSchoolId = $this->resolveSchoolIdByName($schoolName);
            }
        }

        $targetSchoolId = $this->resolveSchoolIdForWrite($authUser, $targetRole, $requestedSchoolId);
        if (! $schoolIdProvided && $schoolCode === '') {
            $this->ensureSchoolNameMatches($payload['school_name'] ?? null, $targetSchoolId);
            $this->ensureSchoolCampMatches(
                $payload['school_camp'] ?? $payload['school_location'] ?? null,
                $targetSchoolId
            );
        }

        $requestedUsername = trim((string) ($payload['username'] ?? ''));
        $requestedUserCode = trim((string) ($payload['user_code'] ?? $payload['admin_id'] ?? ''));
        $restorableUser = $this->resolveRestorableUserForCreate(
            (string) $payload['email'],
            $requestedUsername !== '' ? $requestedUsername : null,
            $requestedUserCode !== '' ? $requestedUserCode : null,
            $targetSchoolId
        );

        if ($restorableUser !== null && $this->normalizedRole($restorableUser) !== $targetRole) {
            throw ValidationException::withMessages([
                'email' => ['A deleted account with this email already exists under a different role. Please restore it instead or use another email.'],
            ]);
        }

        $this->ensureEmailIsUnique($payload['email'], $targetSchoolId, $restorableUser?->id);
        if ($requestedUsername !== '') {
            $this->ensureUsernameIsUnique($requestedUsername, $restorableUser?->id);
        }
        $studentCode = trim((string) ($payload['student_id'] ?? ''));
        $restorableStudent = $restorableUser !== null
            ? Student::query()->withTrashed()->where('user_id', $restorableUser->id)->first()
            : null;

        if ($targetRole === 'student' && $studentCode === '' && $restorableStudent?->student_code) {
            $studentCode = (string) $restorableStudent->student_code;
            $payload['student_id'] = $studentCode;
        }

        if ($targetRole === 'student' && $studentCode === '') {
            $studentCode = $this->generateStudentCode((string) ($payload['email'] ?? $payload['name'] ?? 'student'));
            $payload['student_id'] = $studentCode;
        }

        $resolvedUserCode = $requestedUserCode;
        if ($targetRole === 'student' && $resolvedUserCode === '' && $studentCode !== '') {
            $resolvedUserCode = $studentCode;
        }
        if ($resolvedUserCode === '' && $restorableUser?->user_code) {
            $resolvedUserCode = (string) $restorableUser->user_code;
        }
        if ($resolvedUserCode === '') {
            $resolvedUserCode = $this->generateUserCode(
                $targetRole,
                $targetSchoolId,
                (string) ($payload['email'] ?? $payload['name'] ?? 'user')
            );
        }
        if ($targetRole === 'admin' && trim((string) ($payload['phone'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'phone' => ['phone is required when role is admin.'],
            ]);
        }
        if ($resolvedUserCode !== '') {
            $this->ensureUserCodeIsUnique($resolvedUserCode, $targetSchoolId, $restorableUser?->id);
        }

        $classId = null;
        if ($targetRole === 'student') {
            if ($studentCode !== '') {
                $studentCodeExists = Student::query()->withTrashed()
                    ->where('student_code', $studentCode)
                    ->when($restorableStudent !== null, fn (Builder $query) => $query->whereKeyNot($restorableStudent->id))
                    ->exists();

                if ($studentCodeExists) {
                    throw ValidationException::withMessages([
                        'student_id' => ['This student_id already exists.'],
                    ]);
                }
            }
        }

        $parentIds = array_values(array_unique($payload['parent_ids'] ?? []));
        if ($targetRole === 'student' && $parentIds !== []) {
            if ($targetSchoolId === null) {
                throw ValidationException::withMessages([
                    'school_id' => ['Student role requires a school assignment.'],
                ]);
            }
            $this->ensureParentsAreValid($parentIds, $targetSchoolId);
        }

        $childIds = array_values(array_unique($payload['child_ids'] ?? []));
        if ($targetRole === 'parent' && $childIds !== []) {
            if ($targetSchoolId === null) {
                throw ValidationException::withMessages([
                    'school_id' => ['Parent role requires a school assignment before linking children.'],
                ]);
            }

            $this->ensureChildrenAreValid($childIds, $targetSchoolId);
        }

        $user = DB::transaction(function () use ($payload, $targetRole, $targetSchoolId, $classId, $parentIds, $childIds, $resolvedUserCode, $studentCode, $restorableUser, $restorableStudent): User {
            $passwordHash = $this->resolvePasswordHash($payload['password'] ?? null);

            $user = $restorableUser !== null
                ? User::query()->withTrashed()->findOrFail($restorableUser->id)
                : new User();

            if ($user->exists && method_exists($user, 'trashed') && $user->trashed()) {
                $user->restore();
            }

            $user->fill([
                'role' => $targetRole,
                'username' => $payload['username'] ?? null,
                'user_code' => $resolvedUserCode !== '' ? $resolvedUserCode : null,
                'first_name' => $payload['first_name'] ?? null,
                'last_name' => $payload['last_name'] ?? null,
                'khmer_name' => $payload['khmer_name'] ?? null,
                'name' => $payload['name'],
                'email' => $payload['email'],
                'email_verified_at' => null,
                'password' => $passwordHash,
                'password_hash' => $passwordHash,
                'phone' => $payload['phone'] ?? null,
                'gender' => $payload['gender'] ?? null,
                'dob' => $payload['dob'] ?? null,
                'address' => $payload['address'] ?? null,
                'bio' => $payload['bio'] ?? null,
                'image_url' => $payload['image_url'] ?? null,
                'school_id' => $targetSchoolId,
                'active' => $payload['active'] ?? true,
                'is_active' => $payload['is_active'] ?? ($payload['active'] ?? true),
            ])->save();

            if ($targetRole === 'student') {
                $student = $restorableStudent ?? Student::query()->withTrashed()->firstOrNew(['user_id' => $user->id]);
                if ($student->exists && method_exists($student, 'trashed') && $student->trashed()) {
                    $student->restore();
                }

                $student->fill([
                    'user_id' => $user->id,
                    'student_code' => $studentCode !== '' ? $studentCode : null,
                    'class_id' => $classId,
                    'grade' => $payload['grade'] ?? null,
                    'parent_name' => $payload['parent_name'] ?? null,
                ])->save();

                $student->parents()->sync($parentIds);
            } elseif ($restorableStudent !== null && ! $restorableStudent->trashed()) {
                $restorableStudent->delete();
            }

            if ($targetRole === 'parent') {
                $user->children()->sync($childIds);
            } elseif ($restorableUser !== null) {
                $user->children()->sync([]);
            }

            return $user;
        });

        if ($removeImage) {
            ProfileImageStorage::clearPrimaryForModel($user);
        } elseif ($uploadedImage !== null) {
            $imageUrl = ProfileImageStorage::storeForModel(
                $uploadedImage,
                $user,
                $authUser,
                'profiles/users'
            );
            $user->forceFill(['image_url' => $imageUrl])->save();
        }

        $verificationEmailSent = $this->sendVerificationEmail($user);
        $message = 'User created successfully.';
        if ($verificationEmailSent) {
            $message .= ' Verification email sent.';
        }

        return response()->json([
            'message' => $message,
            'data' => $user->load($this->userDetailRelations()),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $this->normalizeIncomingUserAliases($request, false);

        $authUser = $request->user();
        $this->ensureUserAccessible($authUser, $user);

        $payload = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'school_code' => ['nullable', 'string', 'max:100'],
            'school_name' => ['nullable', 'string', 'max:100'],
            'school_camp' => ['nullable', 'string', 'max:255'],
            'school_location' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'in:super-admin,admin,teacher,student,parent,guardian'],
            'username' => ['nullable', 'string', 'max:50'],
            'user_code' => ['nullable', 'string', 'max:100'],
            'admin_id' => ['nullable', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'khmer_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:100'],
            'admin_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'max:255', PasswordRule::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image' => ProfileImageStorage::uploadValidationRules(),
            'remove_image' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'student_id' => ['nullable', 'string', 'max:100'],
            'grade' => ['nullable', 'string', 'max:20'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_ids' => ['nullable', 'array'],
            'parent_ids.*' => ['integer', 'exists:users,id'],
            'child_ids' => ['nullable', 'array'],
            'child_ids.*' => ['integer', 'exists:students,id'],
        ]);

        $uploadedImage = $request->file('image');
        $removeImage = ($payload['remove_image'] ?? false) === true;

        if ($removeImage) {
            $payload['image_url'] = null;
        }
        unset($payload['image'], $payload['remove_image']);

        $targetRole = $this->normalizeRoleInput((string) ($payload['role'] ?? $user->role));
        $this->ensureAssignableRole($authUser, $targetRole, array_key_exists('role', $payload));
        $authRole = $this->normalizedRole($authUser);

        $schoolIdProvided = array_key_exists('school_id', $payload) && $payload['school_id'] !== null;
        $schoolCode = trim((string) ($payload['school_code'] ?? ''));
        $schoolName = trim((string) ($payload['school_name'] ?? ''));
        $schoolCamp = trim((string) ($payload['school_camp'] ?? $payload['school_location'] ?? ''));
        $requestedSchoolId = array_key_exists('school_id', $payload) ? $payload['school_id'] : $user->school_id;
        if ($requestedSchoolId === null && $authRole === 'super-admin') {
            if ($targetRole === 'admin' && ($schoolName !== '' || $schoolCode !== '')) {
                $requestedSchoolId = $this->resolveOrCreateSchoolIdForAdmin($schoolName, $schoolCamp, $schoolCode);
            } elseif ($schoolCode !== '') {
                $requestedSchoolId = $this->resolveSchoolIdByCode($schoolCode);
            } elseif ($schoolName !== '') {
                $requestedSchoolId = $this->resolveSchoolIdByName($schoolName);
            }
        }

        $targetSchoolId = $this->resolveSchoolIdForWrite(
            $authUser,
            $targetRole,
            $requestedSchoolId
        );
        if (! $schoolIdProvided && $schoolCode === '') {
            $this->ensureSchoolNameMatches($payload['school_name'] ?? null, $targetSchoolId);
            $this->ensureSchoolCampMatches(
                $payload['school_camp'] ?? $payload['school_location'] ?? null,
                $targetSchoolId
            );
        }

        if (isset($payload['email'])) {
            $this->ensureEmailIsUnique($payload['email'], $targetSchoolId, $user->id);
        }
        if (! empty($payload['username'])) {
            $this->ensureUsernameIsUnique((string) $payload['username'], (int) $user->id);
        }

        $existingStudent = $user->studentProfile;
        $studentCode = trim((string) ($payload['student_id'] ?? ($existingStudent?->student_code ?? '')));
        $candidateUserCode = trim((string) ($payload['user_code'] ?? $payload['admin_id'] ?? $user->user_code ?? ''));

        if ($targetRole === 'student' && ! array_key_exists('user_code', $payload) && array_key_exists('student_id', $payload) && $studentCode !== '') {
            $candidateUserCode = $studentCode;
        }

        if ($targetRole === 'admin' && $candidateUserCode === '') {
            throw ValidationException::withMessages([
                'admin_id' => ['admin_id (or user_code) is required when role is admin.'],
            ]);
        }

        if ($candidateUserCode !== '') {
            $this->ensureUserCodeIsUnique($candidateUserCode, $targetSchoolId, (int) $user->id);
        }

        $classId = array_key_exists('class_id', $payload)
            ? ($payload['class_id'] !== null ? (int) $payload['class_id'] : null)
            : ($existingStudent?->class_id !== null ? (int) $existingStudent->class_id : null);

        if ($targetRole === 'student') {
            if ($classId !== null && $classId > 0) {
                $this->ensureClassBelongsToSchool($classId, $targetSchoolId);
            }

            if ($studentCode !== '') {
                $studentCodeExists = Student::query()->withTrashed()
                    ->where('student_code', $studentCode)
                    ->when($existingStudent !== null, fn (Builder $query) => $query->whereKeyNot($existingStudent->id))
                    ->exists();

                if ($studentCodeExists) {
                    throw ValidationException::withMessages([
                        'student_id' => ['This student_id already exists.'],
                    ]);
                }
            }
        }

        $parentIds = array_values(array_unique($payload['parent_ids'] ?? []));
        if ($targetRole === 'student' && array_key_exists('parent_ids', $payload) && $parentIds !== []) {
            if ($targetSchoolId === null) {
                throw ValidationException::withMessages([
                    'school_id' => ['Student role requires a school assignment.'],
                ]);
            }
            $this->ensureParentsAreValid($parentIds, $targetSchoolId);
        }

        $childIds = array_values(array_unique($payload['child_ids'] ?? []));
        if ($targetRole === 'parent' && array_key_exists('child_ids', $payload) && $childIds !== []) {
            if ($targetSchoolId === null) {
                throw ValidationException::withMessages([
                    'school_id' => ['Parent role requires a school assignment before linking children.'],
                ]);
            }

            $this->ensureChildrenAreValid($childIds, $targetSchoolId);
        }

        $emailChanged = array_key_exists('email', $payload)
            && trim((string) $payload['email']) !== ''
            && ! hash_equals(
                mb_strtolower((string) ($user->email ?? '')),
                mb_strtolower(trim((string) $payload['email']))
            );

        DB::transaction(function () use ($user, $payload, $targetRole, $targetSchoolId, $classId, $parentIds, $childIds, $studentCode, $candidateUserCode, $emailChanged): void {
            $updates = [];
            foreach (['username', 'user_code', 'first_name', 'last_name', 'khmer_name', 'name', 'email', 'phone', 'gender', 'dob', 'address', 'bio', 'image_url', 'active', 'is_active'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $updates[$field] = $payload[$field];
                }
            }
            if ($candidateUserCode !== '') {
                $updates['user_code'] = $candidateUserCode;
            }

            $updates['role'] = $targetRole;
            $updates['school_id'] = $targetSchoolId;

            if (! empty($payload['password'])) {
                $passwordHash = Hash::make((string) $payload['password']);
                $updates['password'] = $passwordHash;
                $updates['password_hash'] = $passwordHash;
            }

            if ($emailChanged) {
                $updates['email_verified_at'] = null;
            }

            $user->fill($updates)->save();

            $student = $user->studentProfile;
            if ($targetRole === 'student') {
                if (! $student) {
                    $student = Student::query()->create([
                        'user_id' => $user->id,
                        'student_code' => $studentCode !== '' ? $studentCode : null,
                        'class_id' => $classId,
                        'grade' => $payload['grade'] ?? null,
                        'parent_name' => $payload['parent_name'] ?? null,
                    ]);
                } else {
                    $student->class_id = $classId;
                    if (array_key_exists('grade', $payload)) {
                        $student->grade = $payload['grade'];
                    }
                    if ($studentCode !== '') {
                        $student->student_code = $studentCode;
                    }
                    if (array_key_exists('parent_name', $payload)) {
                        $student->parent_name = $payload['parent_name'];
                    }
                    $student->save();
                }

                if (array_key_exists('parent_ids', $payload)) {
                    $student->parents()->sync($parentIds);
                }
            } elseif ($student) {
                $student->delete();
            }

            if ($targetRole === 'parent') {
                if (array_key_exists('child_ids', $payload)) {
                    $user->children()->sync($childIds);
                }
            } else {
                $user->children()->detach();
            }
        });

        if ($removeImage) {
            ProfileImageStorage::clearPrimaryForModel($user);
        } elseif ($uploadedImage !== null) {
            $imageUrl = ProfileImageStorage::storeForModel(
                $uploadedImage,
                $user,
                $authUser,
                'profiles/users'
            );
            $user->forceFill(['image_url' => $imageUrl])->save();
        }

        $message = 'User updated successfully.';
        if ($emailChanged) {
            $user->tokens()->delete();
            $verificationEmailSent = $this->sendVerificationEmail($user->fresh());
            $message .= $verificationEmailSent
                ? ' Email changed, verification reset, and a new verification email was sent.'
                : ' Email changed and verification was reset.';
        }

        return response()->json([
            'message' => $message,
            'data' => $user->fresh()->load($this->userDetailRelations()),
        ]);
    }

    public function changePassword(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $authUser = $request->user();
        if ($this->normalizedRole($authUser) !== 'super-admin') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $this->ensureUserAccessible($authUser, $user);

        $payload = $request->validate([
            'new_password' => ['required', 'confirmed', PasswordRule::defaults()],
            'new_password_confirmation' => ['required', 'string'],
        ]);

        $passwordHash = Hash::make((string) $payload['new_password']);
        $user->forceFill([
            'password' => $passwordHash,
            'password_hash' => $passwordHash,
        ])->save();

        // Invalidate existing sessions so the new password takes effect immediately.
        $user->tokens()->delete();
        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->delete();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    public function resendVerificationEmail(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $this->ensureUserAccessible($request->user(), $user);

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'This email address is already verified.',
            ]);
        }

        $sent = $this->sendVerificationEmail($user);

        return response()->json([
            'message' => $sent
                ? 'Verification email sent successfully.'
                : 'Unable to send verification email for this user.',
        ], $sent ? 200 : 422);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $authUser = $request->user();
        $this->ensureUserAccessible($authUser, $user);

        if ((int) $authUser->id === (int) $user->id) {
            throw ValidationException::withMessages([
                'user_id' => ['You cannot delete your own account.'],
            ]);
        }

        DB::transaction(function () use ($user): void {
            if ($this->normalizedRole($user) === 'student') {
                $user->studentProfile?->delete();
            }
            $user->delete();
        });

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $authUser = $request->user();
        $authRole = $this->normalizedRole($authUser);
        $payload = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer'],
        ]);

        $userIds = collect($payload['user_ids'])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            throw ValidationException::withMessages([
                'user_ids' => ['Please select at least one user to delete.'],
            ]);
        }

        $query = User::query()->whereIn('id', $userIds->all());
        $this->applyVisibilityScope($query, $authUser);

        $query->whereKeyNot($authUser->id);

        $users = $query->get();

        if ($users->isEmpty()) {
            throw ValidationException::withMessages([
                'user_ids' => ['No deletable users were found in your selection.'],
            ]);
        }

        DB::transaction(function () use ($users): void {
            foreach ($users as $user) {
                if ($this->normalizedRole($user) === 'student') {
                    $user->studentProfile?->delete();
                }

                $user->delete();
            }
        });

        $deletedCount = $users->count();
        $requestedCount = $userIds->count();
        $skippedCount = max(0, $requestedCount - $deletedCount);

        $message = "Deleted {$deletedCount} user(s) successfully.";
        if ($skippedCount > 0) {
            $message .= " Skipped {$skippedCount} user(s) that you cannot delete.";
        }

        return response()->json([
            'message' => $message,
            'data' => [
                'deleted' => $deletedCount,
                'skipped' => $skippedCount,
            ],
        ]);
    }

    public function restore(Request $request, int $userId): JsonResponse
    {
        $authUser = $request->user();
        $user = User::query()->withTrashed()->findOrFail($userId);

        $this->authorize('update', $user);
        $this->ensureUserAccessible($authUser, $user);

        DB::transaction(function () use ($user): void {
            if (method_exists($user, 'trashed') && $user->trashed()) {
                $user->restore();
            }

            if ($this->normalizedRole($user) === 'student') {
                $student = Student::query()->withTrashed()->where('user_id', $user->id)->first();
                if ($student && method_exists($student, 'trashed') && $student->trashed()) {
                    $student->restore();
                }
            }
        });

        return response()->json([
            'message' => 'User restored successfully.',
            'data' => $user->fresh()->load($this->userDetailRelations()),
        ]);
    }

    public function importCsv(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        @ini_set('max_execution_time', '120');

        $payload = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'role' => ['nullable', 'in:teacher,student,parent'],
        ]);

        $authUser = $request->user();
        $authRole = $this->normalizedRole($authUser);
        $defaultRole = (string) ($payload['role'] ?? 'teacher');
        $defaultSchoolId = $authRole === 'super-admin'
            ? (int) ($payload['school_id'] ?? 0)
            : (int) ($authUser->school_id ?? 0);

        if ($defaultSchoolId <= 0 && $authRole !== 'super-admin') {
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
                $rowRole = Str::lower($this->csvValue($row, ['role']));
                if ($rowRole === '') {
                    $rowRole = $defaultRole;
                }
                if (! in_array($rowRole, ['teacher', 'student', 'parent'], true)) {
                    throw ValidationException::withMessages([
                        'role' => ['CSV role must be teacher, student, or parent.'],
                    ]);
                }

                $rowSchoolId = $defaultSchoolId;
                if ($authRole === 'super-admin') {
                    $rowSchoolRaw = $this->csvValue($row, ['school_id', 'school id', 'school']);
                    if ($rowSchoolRaw !== '') {
                        $rowSchoolId = (int) $rowSchoolRaw;
                    }
                }
                if ($rowSchoolId <= 0) {
                    throw ValidationException::withMessages([
                        'school_id' => ['school_id is required for super-admin imports.'],
                    ]);
                }

                $userCode = $this->csvValue($row, ['user_code', 'user code', 'teacher_id', 'teacher id', 'id']);
                $studentCode = $this->csvValue($row, ['student_id', 'student id']);
                $firstName = $this->csvValue($row, ['first_name', 'first name', 'fist_name', 'fist name']);
                $lastName = $this->csvValue($row, ['last_name', 'last name']);
                $khmerName = $this->csvValue($row, ['khmer_name', 'khmer name', 'name_kh', 'kh_name']);
                $phone = $this->csvValue($row, ['phone', 'phone_number', 'phone number']);
                $email = $this->csvValue($row, ['email', 'e-mail']);

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
                        'name' => ['name (or first_name + last_name) is required.'],
                    ]);
                }

                if ($khmerName === '') {
                    $khmerName = $name;
                }

                $classId = null;
                if ($rowRole === 'student') {
                    if ($studentCode === '') {
                        $studentCode = $this->generateStudentCode($email !== '' ? $email : $name);
                    }
                    if ($userCode === '') {
                        $userCode = $studentCode;
                    }
                    $classId = $this->resolveClassIdFromImportRow($row, $rowSchoolId, false);
                }

                $dob = $this->normalizeDobFromCsv($this->csvValue($row, ['dob', 'date_of_birth', 'date of birth', 'birth_date', 'birth date']));
                $gender = $this->normalizeGenderFromCsv($this->csvValue($row, ['gender', 'sex']));
                $parentName = $this->csvValue($row, ['parent_name', 'parent name', 'guardian_name', 'guardian name']);
                $grade = $this->csvValue($row, ['grade', 'grade_level', 'grade level']);
                $password = $this->csvValue($row, ['password']);
                $hasPassword = $password !== '';
                $passwordHash = $hasPassword ? Hash::make($password) : null;

                DB::transaction(function () use (
                    $rowRole,
                    $rowSchoolId,
                    $userCode,
                    $firstName,
                    $lastName,
                    $khmerName,
                    $name,
                    $email,
                    $phone,
                    $gender,
                    $dob,
                    $hasPassword,
                    $passwordHash,
                    $classId,
                    $studentCode,
                    $grade,
                    $parentName,
                    &$created,
                    &$updated
                ): void {
                    $user = null;
                    if ($userCode !== '') {
                        $user = User::query()->withTrashed()
                            ->where('school_id', $rowSchoolId)
                            ->where('user_code', $userCode)
                            ->first();
                    }

                    $existingByEmail = User::query()->withTrashed()
                        ->where('school_id', $rowSchoolId)
                        ->where('email', $email)
                        ->first();

                    if (! $user) {
                        $user = $existingByEmail;
                    }

                    if ($userCode !== '') {
                        $conflictByCode = User::query()->withTrashed()
                            ->where('school_id', $rowSchoolId)
                            ->where('user_code', $userCode)
                            ->when($user !== null, fn (Builder $query) => $query->whereKeyNot($user->id))
                            ->exists();

                        if ($conflictByCode) {
                            throw ValidationException::withMessages([
                                'user_code' => ["User code '{$userCode}' already exists in school {$rowSchoolId}."],
                            ]);
                        }
                    }

                    $conflictByEmail = User::query()->withTrashed()
                        ->where('school_id', $rowSchoolId)
                        ->where('email', $email)
                        ->when($user !== null, fn (Builder $query) => $query->whereKeyNot($user->id))
                        ->exists();

                    if ($conflictByEmail) {
                        throw ValidationException::withMessages([
                            'email' => ["Email '{$email}' already exists in school {$rowSchoolId}."],
                        ]);
                    }

                    $wasCreated = false;
                    if (! $user) {
                        $wasCreated = true;
                        $storedPasswordHash = $passwordHash ?? $this->resolvePasswordHash(null);
                        $user = User::query()->create([
                            'role' => $rowRole,
                            'user_code' => $userCode !== '' ? $userCode : null,
                            'first_name' => $firstName !== '' ? $firstName : null,
                            'last_name' => $lastName !== '' ? $lastName : null,
                            'khmer_name' => $khmerName !== '' ? $khmerName : null,
                            'name' => $name,
                            'email' => $email,
                            'phone' => $phone !== '' ? $phone : null,
                            'gender' => $gender,
                            'dob' => $dob,
                            'password' => $storedPasswordHash,
                            'password_hash' => $storedPasswordHash,
                            'school_id' => $rowSchoolId,
                            'active' => true,
                        ]);
                    } else {
                        if ($user->trashed()) {
                            $user->restore();
                        }

                        $existingRole = $this->normalizedRole($user);
                        if ($existingRole !== $rowRole) {
                            throw ValidationException::withMessages([
                                'role' => ["Cannot import over existing {$user->role} account {$user->email} with role {$rowRole}."],
                            ]);
                        }

                        $updates = [
                            'role' => $rowRole,
                            'user_code' => $userCode !== '' ? $userCode : $user->user_code,
                            'first_name' => $firstName !== '' ? $firstName : $user->first_name,
                            'last_name' => $lastName !== '' ? $lastName : $user->last_name,
                            'khmer_name' => $khmerName !== '' ? $khmerName : $user->khmer_name,
                            'name' => $name,
                            'email' => $email,
                            'phone' => $phone !== '' ? $phone : $user->phone,
                            'gender' => $gender ?? $user->gender,
                            'dob' => $dob ?? $user->dob,
                            'school_id' => $rowSchoolId,
                        ];

                        if ($hasPassword && $passwordHash !== null) {
                            $updates['password'] = $passwordHash;
                            $updates['password_hash'] = $passwordHash;
                        }

                        $user->fill($updates)->save();
                    }

                    if ($rowRole === 'student') {
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
                            'student_code' => $studentCode !== '' ? $studentCode : $student->student_code,
                            'class_id' => $classId,
                            'grade' => $grade !== '' ? $grade : $student->grade,
                            'parent_name' => $parentName !== '' ? $parentName : $student->parent_name,
                        ])->save();
                    } else {
                        $existingStudent = Student::query()->where('user_id', $user->id)->first();
                        if ($existingStudent) {
                            $existingStudent->delete();
                        }
                    }

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
            'message' => 'User CSV import completed.',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors,
            ],
        ], 201);
    }

    private function applyVisibilityScope(Builder $query, User $authUser): void
    {
        $authRole = $this->normalizedRole($authUser);

        if ($authRole === 'super-admin') {
            return;
        }

        if ($authRole === 'admin' && $authUser->school_id) {
            $query->where('school_id', (int) $authUser->school_id)
                ->whereNotIn('role', ['super-admin', 'admin']);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyIndexFilters(Builder $query, array $filters, string $authRole): void
    {
        if (isset($filters['user_id'])) {
            $query->whereKey((int) $filters['user_id']);
        }

        if (isset($filters['school_id']) && $authRole === 'super-admin') {
            $query->where('school_id', (int) $filters['school_id']);
        }

        if (isset($filters['class_id'])) {
            $query->whereHas('studentProfile', fn (Builder $studentQuery) => $studentQuery->where('class_id', (int) $filters['class_id']));
        }

        if (isset($filters['role'])) {
            $query->where('role', $this->normalizeRoleInput((string) $filters['role']));
        }

        if (array_key_exists('active', $filters)) {
            $query->where('active', $filters['active']);
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $scope) use ($search): void {
                $appliedBase = false;
                if (ctype_digit($search)) {
                    $scope->whereKey((int) $search);
                    $appliedBase = true;
                }

                $method = $appliedBase ? 'orWhere' : 'where';
                $scope->{$method}('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('user_code', 'like', "%{$search}%");
            });
        }
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

    private function ensureUserAccessible(User $authUser, User $targetUser): void
    {
        $authRole = $this->normalizedRole($authUser);
        $targetRole = $this->normalizedRole($targetUser);

        if ($authRole === 'super-admin') {
            return;
        }

        if ($authRole !== 'admin' || ! $authUser->school_id) {
            throw ValidationException::withMessages([
                'role' => ['You are not allowed to manage this user.'],
            ]);
        }

        if ($targetRole === 'super-admin') {
            throw ValidationException::withMessages([
                'role' => ['Admin cannot manage super-admin accounts.'],
            ]);
        }

        if ($targetRole === 'admin') {
            throw ValidationException::withMessages([
                'role' => ['Admin cannot manage other admin accounts.'],
            ]);
        }

        if ((int) $targetUser->school_id !== (int) $authUser->school_id) {
            throw ValidationException::withMessages([
                'school_id' => ['User does not belong to your school.'],
            ]);
        }
    }

    private function resolveSchoolIdForWrite(User $authUser, string $targetRole, mixed $requestedSchoolId): ?int
    {
        $authRole = $this->normalizedRole($authUser);

        if ($targetRole === 'super-admin') {
            if ($authRole !== 'super-admin') {
                throw ValidationException::withMessages([
                    'role' => ['Only super-admin can assign super-admin role.'],
                ]);
            }

            return null;
        }

        if ($authRole === 'super-admin') {
            if (! $requestedSchoolId) {
                throw ValidationException::withMessages([
                    'school_id' => ['school_id, school_code, or school_name is required for non-super-admin roles.'],
                ]);
            }

            return (int) $requestedSchoolId;
        }

        if ($authRole === 'admin' && ! $authUser->school_id) {
            throw ValidationException::withMessages([
                'school_id' => ['This admin account has no school assigned. Please ask super-admin to assign a school first.'],
            ]);
        }

        if ($authRole !== 'admin' || ! $authUser->school_id) {
            throw ValidationException::withMessages([
                'role' => ['Only super-admin or admin can manage users.'],
            ]);
        }

        if ($targetRole === 'super-admin') {
            throw ValidationException::withMessages([
                'role' => ['Only super-admin can assign this role.'],
            ]);
        }

        return (int) $authUser->school_id;
    }

    private function resolveRestorableUserForCreate(
        string $email,
        ?string $username,
        ?string $userCode,
        ?int $schoolId
    ): ?User {
        $candidateIds = [];

        $email = trim($email);
        if ($email !== '') {
            $candidateIds = array_merge($candidateIds, User::query()->onlyTrashed()
                ->where('email', $email)
                ->when($schoolId === null, fn (Builder $query) => $query->whereNull('school_id'))
                ->when($schoolId !== null, fn (Builder $query) => $query->where('school_id', $schoolId))
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all());
        }

        if ($username !== null && trim($username) !== '') {
            $candidateIds = array_merge($candidateIds, User::query()->onlyTrashed()
                ->where('username', trim($username))
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all());
        }

        if ($userCode !== null && trim($userCode) !== '') {
            $candidateIds = array_merge($candidateIds, User::query()->onlyTrashed()
                ->where('user_code', trim($userCode))
                ->when($schoolId === null, fn (Builder $query) => $query->whereNull('school_id'))
                ->when($schoolId !== null, fn (Builder $query) => $query->where('school_id', $schoolId))
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all());
        }

        $candidateIds = array_values(array_unique(array_filter($candidateIds)));

        if ($candidateIds === []) {
            return null;
        }

        if (count($candidateIds) > 1) {
            throw ValidationException::withMessages([
                'email' => ['Multiple deleted accounts match this information. Please restore the correct account instead of creating a new one.'],
            ]);
        }

        return User::query()->withTrashed()->find($candidateIds[0]);
    }

    private function ensureEmailIsUnique(string $email, ?int $schoolId, ?int $ignoreUserId = null): void
    {
        $query = User::query()->withTrashed()->where('email', $email);

        if ($schoolId === null) {
            $query->whereNull('school_id');
        } else {
            $query->where('school_id', $schoolId);
        }

        if ($ignoreUserId !== null) {
            $query->whereKeyNot($ignoreUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email is already used in the target school scope.'],
            ]);
        }
    }

    private function ensureUsernameIsUnique(string $username, ?int $ignoreUserId = null): void
    {
        $query = User::query()->withTrashed()->where('username', $username);

        if ($ignoreUserId !== null) {
            $query->whereKeyNot($ignoreUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'username' => ['This username is already used.'],
            ]);
        }
    }

    private function ensureUserCodeIsUnique(string $userCode, ?int $schoolId, ?int $ignoreUserId = null): void
    {
        $query = User::query()->withTrashed()->where('user_code', $userCode);

        if ($schoolId === null) {
            $query->whereNull('school_id');
        } else {
            $query->where('school_id', $schoolId);
        }

        if ($ignoreUserId !== null) {
            $query->whereKeyNot($ignoreUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'user_code' => ['This ID/code already exists in the target school scope.'],
            ]);
        }
    }

    private function generateUserCode(string $role, ?int $schoolId, string $seed): string
    {
        $prefix = match ($role) {
            'super-admin' => 'SUP',
            'admin' => 'ADM',
            'teacher' => 'TCH',
            'student' => 'STU',
            'parent' => 'PAR',
            default => 'USR',
        };

        $normalizedSeed = Str::upper(Str::slug($seed, ''));
        $normalizedSeed = substr($normalizedSeed, 0, 6);

        for ($i = 1; $i <= 9999; $i++) {
            $number = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $code = $normalizedSeed !== ''
                ? "{$prefix}-{$normalizedSeed}-{$number}"
                : "{$prefix}-{$number}";

            $exists = User::query()->withTrashed()
                ->where('user_code', $code)
                ->when($schoolId === null, fn (Builder $query) => $query->whereNull('school_id'))
                ->when($schoolId !== null, fn (Builder $query) => $query->where('school_id', $schoolId))
                ->exists();

            if (! $exists) {
                return $code;
            }
        }

        return $prefix.'-'.strtoupper(substr((string) Str::uuid(), 0, 8));
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

    private function ensureClassBelongsToSchool(int $classId, ?int $schoolId): void
    {
        if ($schoolId === null) {
            throw ValidationException::withMessages([
                'school_id' => ['school_id is required for student role.'],
            ]);
        }

        $class = SchoolClass::query()->find($classId);
        if (! $class || (int) $class->school_id !== $schoolId) {
            throw ValidationException::withMessages([
                'class_id' => ['Selected class does not belong to the target school.'],
            ]);
        }
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
            $this->ensureClassBelongsToSchool($classId, $schoolId);

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
                'dob' => ["Invalid dob value '{$value}'. Use date format like YYYY-MM-DD."],
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
            $base = 'user';
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

    /**
     * @param  array<int, int>  $parentIds
     */
    private function ensureParentsAreValid(array $parentIds, int $schoolId): void
    {
        $parents = User::query()
            ->whereIn('id', $parentIds)
            ->get(['id', 'role', 'school_id']);

        if ($parents->count() !== count($parentIds)) {
            throw ValidationException::withMessages([
                'parent_ids' => ['One or more parent_ids do not exist.'],
            ]);
        }

        $invalid = $parents->first(function (User $parent) use ($schoolId): bool {
            return ! in_array($parent->role, ['parent', 'guardian'], true) || (int) $parent->school_id !== $schoolId;
        });

        if ($invalid) {
            throw ValidationException::withMessages([
                'parent_ids' => ['All parent_ids must be parent users from the same school.'],
            ]);
        }
    }

    /**
     * @param  array<int, int>  $childIds
     */
    private function ensureChildrenAreValid(array $childIds, int $schoolId): void
    {
        $children = Student::query()
            ->with('user:id,school_id')
            ->whereIn('id', $childIds)
            ->get(['id', 'user_id']);

        if ($children->count() !== count($childIds)) {
            throw ValidationException::withMessages([
                'child_ids' => ['One or more child_ids do not exist.'],
            ]);
        }

        $invalid = $children->first(function (Student $child) use ($schoolId): bool {
            return (int) ($child->user?->school_id ?? 0) !== $schoolId;
        });

        if ($invalid) {
            throw ValidationException::withMessages([
                'child_ids' => ['All child_ids must be students from the same school.'],
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function userDetailRelations(): array
    {
        return [
            'school',
            'studentProfile.class',
            'studentProfile.parents',
            'children.user',
            'children.class',
            'teachingClasses',
        ];
    }

    private function normalizeRoleInput(string $role): string
    {
        $value = Str::lower(trim($role));

        return $value === 'guardian' ? 'parent' : $value;
    }

    private function ensureAssignableRole(User $authUser, string $targetRole, bool $isRoleAssignment): void
    {
        $authRole = $this->normalizedRole($authUser);

        if ($targetRole === 'super-admin' && $authRole !== 'super-admin') {
            throw ValidationException::withMessages([
                'role' => ['Only super-admin can assign super-admin role.'],
            ]);
        }

        if ($targetRole === 'admin' && $isRoleAssignment && $authRole !== 'super-admin') {
            throw ValidationException::withMessages([
                'role' => ['Only super-admin can assign admin role.'],
            ]);
        }
    }

    private function normalizedRole(User $user): string
    {
        return $this->normalizeRoleInput((string) ($user->role ?? ''));
    }

    private function ensureSchoolNameMatches(?string $schoolName, ?int $schoolId): void
    {
        $name = trim((string) $schoolName);
        if ($name === '') {
            return;
        }

        if ($schoolId === null) {
            throw ValidationException::withMessages([
                'school_name' => ['school_name cannot be used without a selected school.'],
            ]);
        }

        $match = School::query()
            ->whereKey($schoolId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->exists();

        if (! $match) {
            throw ValidationException::withMessages([
                'school_name' => ['school_name does not match the selected school_id.'],
            ]);
        }
    }

    private function ensureSchoolCodeMatches(?string $schoolCode, ?int $schoolId): void
    {
        $code = trim((string) $schoolCode);
        if ($code === '') {
            return;
        }

        if ($schoolId === null) {
            throw ValidationException::withMessages([
                'school_code' => ['school_code cannot be used without a selected school.'],
            ]);
        }

        $match = School::query()
            ->whereKey($schoolId)
            ->whereRaw('LOWER(COALESCE(school_code, \'\')) = ?', [Str::lower($code)])
            ->exists();

        if (! $match) {
            throw ValidationException::withMessages([
                'school_code' => ['school_code does not match the selected school.'],
            ]);
        }
    }

    private function ensureSchoolCampMatches(?string $schoolCamp, ?int $schoolId): void
    {
        $camp = trim((string) $schoolCamp);
        if ($camp === '') {
            return;
        }

        if ($schoolId === null) {
            throw ValidationException::withMessages([
                'school_camp' => ['school_camp cannot be used without a selected school.'],
            ]);
        }

        $match = School::query()
            ->whereKey($schoolId)
            ->whereRaw('LOWER(COALESCE(location, \'\')) = ?', [Str::lower($camp)])
            ->exists();

        if (! $match) {
            throw ValidationException::withMessages([
                'school_camp' => ['school_camp does not match the selected school.'],
            ]);
        }
    }

    private function resolveSchoolIdByName(string $schoolName): int
    {
        $name = trim($schoolName);
        $matches = School::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->pluck('id');

        if ($matches->count() === 1) {
            return (int) $matches->first();
        }

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'school_name' => ['Multiple schools use this name. Please use school_id or school_code.'],
            ]);
        }

        throw ValidationException::withMessages([
            'school_name' => ['school_name was not found. Please check spelling or use school_id/school_code.'],
        ]);
    }

    private function resolveSchoolIdByCode(string $schoolCode): int
    {
        $code = trim($schoolCode);
        $schoolId = School::query()
            ->whereRaw('LOWER(school_code) = ?', [Str::lower($code)])
            ->value('id');

        if ($schoolId === null) {
            throw ValidationException::withMessages([
                'school_code' => ['school_code was not found. Please check spelling or create the school first.'],
            ]);
        }

        return (int) $schoolId;
    }

    private function resolveOrCreateSchoolIdForAdmin(string $schoolName, string $schoolCamp = '', string $schoolCode = ''): int
    {
        $name = trim($schoolName);
        $code = trim($schoolCode);
        $camp = trim($schoolCamp);

        if ($code !== '') {
            $existingByCode = School::query()
                ->whereRaw('LOWER(school_code) = ?', [Str::lower($code)])
                ->first();

            if ($existingByCode !== null) {
                if ($name !== '' && Str::lower((string) $existingByCode->name) !== Str::lower($name)) {
                    throw ValidationException::withMessages([
                        'school_name' => ['school_name does not match the existing school_code.'],
                    ]);
                }

                if ($camp !== '' && Str::lower((string) ($existingByCode->location ?? '')) !== Str::lower($camp)) {
                    throw ValidationException::withMessages([
                        'school_camp' => ['school_camp does not match the existing school_code.'],
                    ]);
                }

                return (int) $existingByCode->id;
            }
        }

        if ($name === '') {
            throw ValidationException::withMessages([
                'school_name' => ['school_name is required when creating admin without an existing school_id.'],
            ]);
        }

        $existing = School::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->get();

        if ($existing->count() > 1) {
            throw ValidationException::withMessages([
                'school_name' => ['Multiple schools use this name. Please use school_id or school_code.'],
            ]);
        }

        if ($existing->count() === 1) {
            $school = $existing->first();

            if ($code !== '') {
                $existingCode = trim((string) ($school?->school_code ?? ''));

                if ($existingCode !== '' && Str::lower($existingCode) !== Str::lower($code)) {
                    throw ValidationException::withMessages([
                        'school_code' => ['school_code does not match the existing school name.'],
                    ]);
                }

                if ($existingCode === '') {
                    $school->forceFill(['school_code' => $code])->save();
                }
            }

            if ($camp !== '' && Str::lower((string) ($school?->location ?? '')) !== Str::lower($camp)) {
                throw ValidationException::withMessages([
                    'school_camp' => ['school_camp does not match the existing school.'],
                ]);
            }

            return (int) $school->id;
        }

        $school = School::query()->create([
            'name' => $name,
            'school_code' => $code !== '' ? $code : null,
            'location' => $camp !== '' ? $camp : null,
            'config_details' => [],
        ]);

        return (int) $school->id;
    }

    private function sendVerificationEmail(User $user): bool
    {
        $email = trim((string) $user->email);
        if ($email === '') {
            return false;
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Unable to send verification email.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        return true;
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

    private function normalizeIncomingUserAliases(Request $request, bool $isCreate): void
    {
        $input = $request->all();
        $adminName = trim((string) ($input['admin_name'] ?? ''));
        $adminId = trim((string) ($input['admin_id'] ?? ''));
        $schoolIdInput = trim((string) ($input['school_id'] ?? ''));
        $schoolCode = trim((string) ($input['school_code'] ?? ''));
        $schoolName = trim((string) ($input['school_name'] ?? ''));
        $schoolCamp = trim((string) ($input['school_camp'] ?? $input['school_location'] ?? ''));

        if ($schoolCode === '' && $schoolIdInput !== '' && ! preg_match('/^\d+$/', $schoolIdInput)) {
            $input['school_code'] = $schoolIdInput;
            unset($input['school_id']);
            $schoolCode = $schoolIdInput;
        }

        if (($input['name'] ?? null) === null && $adminName !== '') {
            $input['name'] = $adminName;
        }

        if (($input['user_code'] ?? null) === null && $adminId !== '') {
            $input['user_code'] = $adminId;
        }

        if (($input['school_location'] ?? null) === null && $schoolCamp !== '') {
            $input['school_location'] = $schoolCamp;
        }

        if ($isCreate && ! isset($input['role']) && ($adminName !== '' || $adminId !== '' || $schoolName !== '' || $schoolCamp !== '' || $schoolCode !== '')) {
            $input['role'] = 'admin';
        }

        $request->replace($input);
    }
}
