<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimetableCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'teacher_id' => ['nullable', 'integer'],
            'day_of_week' => ['nullable', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $api->get($request, '/api/timetables', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.timetables.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
            'userRole' => $request->user()->role,
        ] + $this->loadAcademicSelectOptions($request, $api, true, true, false));
    }

    public function create(Request $request, InternalApiClient $api): View
    {
        return view('web.crud.timetables.form', [
            'mode' => 'create',
            'item' => null,
            'userRole' => $request->user()->role,
        ] + $this->loadAcademicSelectOptions($request, $api, true, true, false));
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $result = $api->post($request, '/api/timetables', $payload);

        if ($result['status'] !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.timetables.index', [], false))->with('success', 'Timetable created successfully.');
    }

    public function edit(Request $request, int $timetable, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/timetables/'.$timetable);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.timetables.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.timetables.form', [
            'mode' => 'edit',
            'item' => $result['data']['data'] ?? null,
            'userRole' => $request->user()->role,
        ] + $this->loadAcademicSelectOptions($request, $api, true, true, false));
    }

    public function update(Request $request, int $timetable, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $result = $api->put($request, '/api/timetables/'.$timetable, $payload);

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.timetables.index', [], false))->with('success', 'Timetable updated successfully.');
    }

    public function destroy(Request $request, int $timetable, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/timetables/'.$timetable);

        if ($result['status'] !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.timetables.index', [], false))->with('success', 'Timetable deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $payload = $request->validate([
            'class_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'teacher_id' => ['nullable', 'integer'],
            'day_of_week' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i'],
        ]);

        if ($request->user()->role === 'teacher') {
            unset($payload['teacher_id']);
        }

        return $payload;
    }
}
