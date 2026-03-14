<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'school_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $api->get($request, '/api/subjects', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.subjects.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
            'userRole' => $request->user()->role,
        ]);
    }

    public function create(Request $request): View
    {
        return view('web.crud.subjects.form', [
            'mode' => 'create',
            'item' => null,
            'userRole' => $request->user()->role,
        ]);
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $result = $api->post($request, '/api/subjects', $payload);

        if ($result['status'] !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.subjects.index', [], false))->with('success', 'Subject created successfully.');
    }

    public function edit(Request $request, int $subject, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/subjects/'.$subject);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.subjects.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.subjects.form', [
            'mode' => 'edit',
            'item' => $result['data']['data'] ?? null,
            'userRole' => $request->user()->role,
        ]);
    }

    public function update(Request $request, int $subject, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $result = $api->put($request, '/api/subjects/'.$subject, $payload);

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.subjects.index', [], false))->with('success', 'Subject updated successfully.');
    }

    public function destroy(Request $request, int $subject, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/subjects/'.$subject);

        if ($result['status'] !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.subjects.index', [], false))->with('success', 'Subject deleted successfully.');
    }

    public function installKhmerCore(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'school_id' => ['nullable', 'integer'],
            'extra_subjects_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $apiPayload = [];
        if ($request->user()->role === 'super-admin' && ! empty($payload['school_id'])) {
            $apiPayload['school_id'] = (int) $payload['school_id'];
        }

        $extraSubjects = collect(preg_split('/[\\r\\n,]+/', (string) ($payload['extra_subjects_text'] ?? '')) ?: [])
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($extraSubjects !== []) {
            $apiPayload['extra_subjects'] = $extraSubjects;
        }

        $result = $api->post($request, '/api/subjects/install-khmer-core', $apiPayload);

        if (($result['status'] ?? 0) !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        $data = $result['data']['data'] ?? [];
        $message = sprintf(
            'Subjects installed. Created: %d, Existing: %d',
            (int) ($data['created'] ?? 0),
            (int) ($data['existing'] ?? 0)
        );

        return redirect()->away(route('panel.subjects.index', [], false))->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $payload = $request->validate([
            'school_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:100'],
            'full_score' => ['required', 'numeric', 'min:1', 'max:1000'],
        ]);

        if ($request->user()->role !== 'super-admin') {
            unset($payload['school_id']);
        }

        return $payload;
    }
}
