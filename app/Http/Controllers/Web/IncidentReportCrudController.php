<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncidentReportCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'student_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'max:100'],
            'acknowledged' => ['nullable', 'in:0,1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $api->get($request, '/api/incident-reports', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.incident-reports.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
        ] + $this->loadAcademicSelectOptions($request, $api, false, false, true));
    }

    public function create(Request $request, InternalApiClient $api): View
    {
        return view('web.crud.incident-reports.form', [
            'mode' => 'create',
            'item' => null,
        ] + $this->loadAcademicSelectOptions($request, $api, false, false, true));
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $result = $api->post($request, '/api/incident-reports', $payload);

        if ($result['status'] !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.incident-reports.index', [], false))->with('success', 'Incident report created successfully.');
    }

    public function edit(Request $request, int $incidentReport, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/incident-reports/'.$incidentReport);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.incident-reports.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.incident-reports.form', [
            'mode' => 'edit',
            'item' => $result['data']['data'] ?? null,
        ] + $this->loadAcademicSelectOptions($request, $api, false, false, true));
    }

    public function update(Request $request, int $incidentReport, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $result = $api->put($request, '/api/incident-reports/'.$incidentReport, $payload);

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.incident-reports.index', [], false))->with('success', 'Incident report updated successfully.');
    }

    public function destroy(Request $request, int $incidentReport, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/incident-reports/'.$incidentReport);

        if ($result['status'] !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.incident-reports.index', [], false))->with('success', 'Incident report deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'student_id' => ['required', 'integer'],
            'description' => ['required', 'string'],
            'date' => ['nullable', 'date'],
            'type' => ['nullable', 'string', 'max:100'],
            'acknowledged' => ['nullable', 'boolean'],
        ]);
    }
}
