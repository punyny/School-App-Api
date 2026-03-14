<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $api->get($request, '/api/schools', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.schools.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('web.crud.schools.form', [
            'mode' => 'create',
            'item' => null,
        ]);
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $result = $api->post($request, '/api/schools', $payload);

        if ($result['status'] !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.schools.index', [], false))->with('success', 'School created successfully.');
    }

    public function edit(Request $request, int $school, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/schools/'.$school);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.schools.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.schools.form', [
            'mode' => 'edit',
            'item' => $result['data']['data'] ?? null,
        ]);
    }

    public function update(Request $request, int $school, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $result = $api->put($request, '/api/schools/'.$school, $payload);

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.schools.index', [], false))->with('success', 'School updated successfully.');
    }

    public function destroy(Request $request, int $school, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/schools/'.$school);

        if ($result['status'] !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.schools.index', [], false))->with('success', 'School deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'school_code' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'config_details' => ['nullable', 'string'],
        ]);

        $payload['config_details'] = $this->normalizeConfigDetails($payload['config_details'] ?? null);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeConfigDetails(mixed $value): array
    {
        $rawConfig = trim((string) $value);
        if ($rawConfig === '') {
            return [];
        }

        $decoded = json_decode($rawConfig, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return [
            'notes' => $rawConfig,
        ];
    }
}
