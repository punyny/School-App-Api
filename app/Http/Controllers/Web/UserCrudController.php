<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Models\User;
use App\Services\InternalApiClient;
use App\Support\PasswordRule;
use App\Support\ProfileImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'school_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'role' => ['nullable', 'in:super-admin,admin,teacher,student,parent'],
            'active' => ['nullable', 'in:0,1'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'string', 'max:20'],
        ]);

        $rawPerPage = trim((string) ($filters['per_page'] ?? ''));
        if ($rawPerPage !== '') {
            if (Str::lower($rawPerPage) === 'all') {
                $filters['per_page'] = 'all';
            } elseif (ctype_digit($rawPerPage) && (int) $rawPerPage >= 1 && (int) $rawPerPage <= 5000) {
                $filters['per_page'] = (string) ((int) $rawPerPage);
            } else {
                throw ValidationException::withMessages([
                    'per_page' => ['per_page must be 20 or all.'],
                ]);
            }
        } else {
            unset($filters['per_page']);
        }

        $result = $api->get($request, '/api/users', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.users.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
            'userRole' => $request->user()->role,
            'schoolOptions' => $request->user()->role === 'super-admin'
                ? $this->loadSchoolSelectOptions($request, $api)
                : [],
        ] + $this->loadAcademicSelectOptions($request, $api, true, false, false));
    }

    public function create(Request $request, InternalApiClient $api): View
    {
        $roleOptions = $this->allowedRolesFor($request->user());
        $defaultRole = $this->resolveDefaultRole($request, $roleOptions);

        return view('web.crud.users.form', [
            'mode' => 'create',
            'item' => null,
            'userRole' => $request->user()->role,
            'roleOptions' => $roleOptions,
            'defaultRole' => $defaultRole,
            'schoolOptions' => $request->user()->role === 'super-admin'
                ? $this->loadSchoolSelectOptions($request, $api)
                : [],
        ] + $this->loadAcademicSelectOptions($request, $api, true, false, true));
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        if ($guard = $this->ensureAdminHasSchoolContext($request)) {
            return $guard;
        }

        $payload = $this->validatePayload($request, true);
        $result = $api->post($request, '/api/users', $payload);

        if ($result['status'] !== 201) {
            if ($redirect = $this->restoreDeletedUserAndRedirectToEdit($request, $api, $payload, $result)) {
                return $redirect;
            }

            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.users.index', [], false))
            ->with('success', (string) ($result['data']['message'] ?? 'User created successfully.'));
    }

    public function edit(Request $request, int $user, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/users/'.$user);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.users.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.users.form', [
            'mode' => 'edit',
            'item' => $result['data']['data'] ?? null,
            'userRole' => $request->user()->role,
            'roleOptions' => $this->allowedRolesFor($request->user()),
            'defaultRole' => $result['data']['data']['role'] ?? 'teacher',
            'schoolOptions' => $request->user()->role === 'super-admin'
                ? $this->loadSchoolSelectOptions($request, $api)
                : [],
        ] + $this->loadAcademicSelectOptions($request, $api, true, false, true));
    }

    public function show(Request $request, int $user, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/users/'.$user);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.users.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.users.show', [
            'item' => $result['data']['data'] ?? null,
            'userRole' => $request->user()->role,
        ]);
    }

    public function update(Request $request, int $user, InternalApiClient $api): RedirectResponse
    {
        if ($guard = $this->ensureAdminHasSchoolContext($request)) {
            return $guard;
        }

        $payload = $this->validatePayload($request, false);
        $result = $api->put($request, '/api/users/'.$user, $payload);

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.users.index', [], false))
            ->with('success', (string) ($result['data']['message'] ?? 'User updated successfully.'));
    }

    public function resendVerification(Request $request, int $user, InternalApiClient $api): RedirectResponse
    {
        $result = $api->post($request, '/api/users/'.$user.'/resend-verification-email');

        if (($result['status'] ?? 0) !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return back()->with('success', (string) ($result['data']['message'] ?? 'Verification email sent successfully.'));
    }

    public function destroy(Request $request, int $user, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/users/'.$user);

        if ($result['status'] !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.users.index', [], false))->with('success', 'User deleted successfully.');
    }

    public function bulkDestroy(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer'],
        ]);

        $result = $api->post($request, '/api/users/bulk-delete', $payload);

        if (($result['status'] ?? 0) !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.users.index', [], false))
            ->with('success', (string) ($result['data']['message'] ?? 'Selected users deleted successfully.'));
    }

    public function importCsv(Request $request, InternalApiClient $api): RedirectResponse
    {
        if ($guard = $this->ensureAdminHasSchoolContext($request)) {
            return $guard;
        }

        $payload = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
            'role' => ['nullable', 'in:teacher,student,parent'],
            'school_id' => ['nullable', 'integer'],
        ]);

        $apiPayload = [
            'file' => $request->file('csv_file'),
            'role' => $payload['role'] ?? 'teacher',
        ];

        if ($request->user()->role === 'super-admin' && isset($payload['school_id'])) {
            $apiPayload['school_id'] = (int) $payload['school_id'];
        }

        $result = $api->post($request, '/api/users/import/csv', $apiPayload);

        if (($result['status'] ?? 0) !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        $data = $result['data']['data'] ?? [];
        $created = (int) ($data['created'] ?? 0);
        $updated = (int) ($data['updated'] ?? 0);
        $errors = is_array($data['errors'] ?? null) ? $data['errors'] : [];

        if (($created + $updated) === 0 && $errors !== []) {
            $messages = collect($errors)
                ->take(5)
                ->map(function ($row): string {
                    $line = (int) ($row['line'] ?? 0);
                    $message = (string) ($row['message'] ?? 'Unknown CSV error');

                    return $line > 0 ? "Line {$line}: {$message}" : $message;
                })
                ->all();

            return back()->withInput()->withErrors(['csv' => $messages]);
        }

        $message = sprintf(
            'CSV import completed. Created: %d, Updated: %d, Errors: %d',
            $created,
            $updated,
            count($errors)
        );

        if ($errors !== []) {
            $firstError = (string) ($errors[0]['message'] ?? '');
            if ($firstError !== '') {
                $message .= ' | First error: '.$firstError;
            }
        }

        return redirect()->away(route('panel.users.index', [], false))->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $isCreate): array
    {
        if (! $isCreate) {
            $rawPassword = $request->input('password');
            if (is_string($rawPassword) && trim($rawPassword) === '') {
                $request->merge(['password' => null]);
            }
        }

        $passwordRules = $isCreate
            ? ['required', 'string', 'max:255', PasswordRule::defaults()]
            : ['nullable', 'string', 'max:255', PasswordRule::defaults()];

        $roleOptions = $this->allowedRolesFor($request->user());

        $payload = $request->validate([
            'school_id' => ['nullable', 'integer'],
            'role' => ['required', Rule::in($roleOptions)],
            'user_code' => ['nullable', 'string', 'max:100'],
            'admin_id' => ['nullable', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'khmer_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:100'],
            'admin_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'password' => $passwordRules,
            'phone' => ['nullable', 'string', 'max:20'],
            'telegram_chat_id' => ['nullable', 'string', 'max:64'],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image' => ProfileImageStorage::uploadValidationRules(),
            'remove_image' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
            'class_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'string', 'max:100'],
            'grade' => ['nullable', 'string', 'max:20'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_ids' => ['nullable', 'string'],
            'child_ids' => ['nullable', 'array'],
            'child_ids.*' => ['integer'],
        ]);

        if (($payload['remove_image'] ?? false) === true) {
            $payload['image_url'] = null;
        }
        if (! $request->hasFile('image')) {
            unset($payload['image']);
        }
        unset($payload['remove_image']);

        $role = (string) ($payload['role'] ?? '');
        $englishName = trim((string) ($payload['name'] ?? ''));
        if ($englishName === '') {
            $fullFromParts = trim(
                trim((string) ($payload['first_name'] ?? '')).' '.trim((string) ($payload['last_name'] ?? ''))
            );
            if ($fullFromParts !== '') {
                $payload['name'] = $fullFromParts;
            } elseif (trim((string) ($payload['khmer_name'] ?? '')) !== '') {
                $payload['name'] = trim((string) ($payload['khmer_name'] ?? ''));
            }
        }

        if ($role === 'admin') {
            $adminName = trim((string) ($payload['admin_name'] ?? ''));
            $adminId = trim((string) ($payload['admin_id'] ?? ''));
            $resolvedCode = trim((string) ($payload['user_code'] ?? $adminId));

            if ($isCreate && $adminName === '') {
                throw ValidationException::withMessages([
                    'admin_name' => ['admin_name is required when role is admin.'],
                ]);
            }
            if ($isCreate && trim((string) ($payload['phone'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'phone' => ['phone is required when role is admin.'],
                ]);
            }

            if ($adminName !== '') {
                $payload['name'] = $adminName;
            }
            if ($resolvedCode !== '') {
                $payload['user_code'] = $resolvedCode;
            }
        }

        if ($role !== 'admin' && $isCreate && trim((string) ($payload['name'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'name' => ['name is required.'],
            ]);
        }

        if ($request->user()->role !== 'super-admin') {
            unset($payload['school_id']);
        } else {
            if ($role === 'super-admin') {
                $payload['school_id'] = null;
            } elseif (empty($payload['school_id'])) {
                throw ValidationException::withMessages([
                    'school_id' => ['Please select a school for this role.'],
                ]);
            }
        }

        if (($payload['role'] ?? '') === 'parent') {
            unset($payload['class_id'], $payload['student_id'], $payload['grade'], $payload['parent_name'], $payload['parent_ids']);
            unset($payload['admin_name'], $payload['admin_id']);

            $payload['child_ids'] = collect($payload['child_ids'] ?? [])
                ->map(fn (mixed $id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();

            if ($payload['child_ids'] === []) {
                unset($payload['child_ids']);
            }

            return $payload;
        }

        if (($payload['role'] ?? '') !== 'student') {
            unset($payload['class_id'], $payload['student_id'], $payload['grade'], $payload['parent_name'], $payload['parent_ids'], $payload['child_ids']);

            return $payload;
        }

        $rawParentIds = trim((string) Arr::get($payload, 'parent_ids', ''));
        if ($rawParentIds === '') {
            unset($payload['parent_ids']);

            unset($payload['admin_name'], $payload['admin_id']);

            return $payload;
        }

        $parsed = collect(explode(',', $rawParentIds))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($parsed === []) {
            throw ValidationException::withMessages([
                'parent_ids' => ['parent_ids must contain valid comma-separated user IDs.'],
            ]);
        }

        $payload['parent_ids'] = $parsed;
        unset($payload['child_ids']);
        unset($payload['admin_name'], $payload['admin_id']);

        return $payload;
    }

    private function ensureAdminHasSchoolContext(Request $request): ?RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $role = strtolower(trim((string) ($user->role ?? '')));
        if ($role !== 'admin') {
            return null;
        }

        if (! empty($user->school_id)) {
            return null;
        }

        return back()->withInput()->withErrors([
            'school_id' => ['Your admin account has no school assigned. Please login as super-admin and assign a school first.'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{status:int, data:array<string,mixed>|null}  $result
     */
    private function restoreDeletedUserAndRedirectToEdit(
        Request $request,
        InternalApiClient $api,
        array $payload,
        array $result
    ): ?RedirectResponse {
        $errorBag = $this->extractErrors($result);
        $messages = collect($errorBag)->flatten()->filter(fn ($message) => is_string($message));

        $shouldRestore = $messages->contains(
            fn (string $message): bool => str_contains($message, 'Please restore it instead')
        );

        if (! $shouldRestore) {
            return null;
        }

        $deletedUser = $this->findDeletedUserByPayload($request, $payload);
        if (! $deletedUser) {
            return null;
        }

        $restoreResult = $api->post($request, '/api/users/'.$deletedUser->id.'/restore');
        if (($restoreResult['status'] ?? 0) !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($restoreResult));
        }

        return redirect()->away(route('panel.users.edit', ['user' => $deletedUser->id], false))
            ->with('success', 'Deleted account restored successfully. Please update the role and save.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findDeletedUserByPayload(Request $request, array $payload): ?User
    {
        $email = trim((string) ($payload['email'] ?? ''));
        if ($email === '') {
            return null;
        }

        $query = User::query()->onlyTrashed()->where('email', $email);
        $actor = $request->user();

        if (($actor?->role ?? null) === 'super-admin') {
            if (array_key_exists('school_id', $payload)) {
                $schoolId = $payload['school_id'];
                if ($schoolId === null || $schoolId === '') {
                    $query->whereNull('school_id');
                } else {
                    $query->where('school_id', (int) $schoolId);
                }
            }
        } elseif ($actor?->school_id) {
            $query->where('school_id', (int) $actor->school_id);
        }

        return $query->orderByDesc('deleted_at')->first();
    }

    /**
     * @param  array<int, string>  $roleOptions
     */
    private function resolveDefaultRole(Request $request, array $roleOptions): string
    {
        $requestedRole = (string) $request->query('role', '');
        if ($requestedRole !== '' && in_array($requestedRole, $roleOptions, true)) {
            return $requestedRole;
        }

        if (in_array('teacher', $roleOptions, true)) {
            return 'teacher';
        }

        return $roleOptions[0] ?? 'teacher';
    }

    /**
     * @return array<int, string>
     */
    private function allowedRolesFor(User $actor): array
    {
        if ($actor->role === 'super-admin') {
            return ['super-admin', 'admin', 'teacher', 'student', 'parent'];
        }

        return ['teacher', 'student', 'parent'];
    }
}
