<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use App\Support\PasswordRule;
use App\Support\ProfileImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class StudentCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'school_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'in:0,1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $api->get($request, '/api/students', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.students.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
            'userRole' => $request->user()->role,
        ] + $this->loadAcademicSelectOptions($request, $api, true, false, false));
    }

    public function create(Request $request, InternalApiClient $api): View
    {
        return view('web.crud.students.form', [
            'mode' => 'create',
            'item' => null,
            'userRole' => $request->user()->role,
        ] + $this->loadAcademicSelectOptions($request, $api, true, false, false));
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request, true);
        $result = $api->post($request, '/api/students', $payload);

        if ($result['status'] !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.students.index', [], false))->with('success', 'Student created successfully.');
    }

    public function edit(Request $request, int $student, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/students/'.$student);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.students.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.students.form', [
            'mode' => 'edit',
            'item' => $result['data']['data'] ?? null,
            'userRole' => $request->user()->role,
        ] + $this->loadAcademicSelectOptions($request, $api, true, false, false));
    }

    public function show(Request $request, int $student, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/students/'.$student);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.students.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.students.show', [
            'item' => $result['data']['data'] ?? null,
            'userRole' => $request->user()->role,
        ]);
    }

    public function update(Request $request, int $student, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request, false);
        $result = $api->put($request, '/api/students/'.$student, $payload);

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.students.index', [], false))->with('success', 'Student updated successfully.');
    }

    public function destroy(Request $request, int $student, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/students/'.$student);

        if ($result['status'] !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.students.index', [], false))->with('success', 'Student deleted successfully.');
    }

    public function importCsv(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
            'school_id' => ['nullable', 'integer'],
        ]);

        $apiPayload = [
            'file' => $request->file('csv_file'),
        ];

        if ($request->user()->role === 'super-admin' && isset($payload['school_id'])) {
            $apiPayload['school_id'] = (int) $payload['school_id'];
        }

        $result = $api->post($request, '/api/students/import/csv', $apiPayload);

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
            'Student CSV import completed. Created: %d, Updated: %d, Errors: %d',
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

        return redirect()->away(route('panel.students.index', [], false))->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $isCreate): array
    {
        $passwordRule = $isCreate
            ? ['required', 'string', 'max:255', PasswordRule::defaults()]
            : ['nullable', 'string', 'max:255', PasswordRule::defaults()];
        $studentIdRule = ['nullable', 'string', 'max:100'];
        $khmerNameRule = $isCreate
            ? ['required', 'string', 'max:255']
            : ['nullable', 'string', 'max:255'];

        $payload = $request->validate([
            'school_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'student_id' => $studentIdRule,
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'khmer_name' => $khmerNameRule,
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'password' => $passwordRule,
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
            'parent_ids' => ['nullable', 'string'],
        ]);

        if (($payload['remove_image'] ?? false) === true) {
            $payload['image_url'] = null;
        }
        if (! $request->hasFile('image')) {
            unset($payload['image']);
        }
        unset($payload['remove_image']);

        if ($request->user()->role !== 'super-admin') {
            unset($payload['school_id']);
        }

        if (! $isCreate && empty($payload['password'])) {
            unset($payload['password']);
        }

        $rawParentIds = trim((string) Arr::get($payload, 'parent_ids', ''));
        if ($rawParentIds === '') {
            unset($payload['parent_ids']);
        } else {
            $payload['parent_ids'] = collect(explode(',', $rawParentIds))
                ->map(fn (string $id): int => (int) trim($id))
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        return $payload;
    }
}
