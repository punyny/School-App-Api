<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:P,A,L'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $api->get($request, '/api/attendance', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.attendance.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
        ] + $this->loadAcademicSelectOptions($request, $api, true, false, true));
    }

    public function create(Request $request, InternalApiClient $api): View
    {
        return view('web.crud.attendance.form', [
            'mode' => 'create',
            'item' => null,
            'userRole' => $request->user()->role,
        ] + $this->loadAcademicSelectOptions($request, $api, true, false, true));
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'student_id' => ['required', 'integer'],
            'class_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['nullable', 'date_format:H:i'],
            'status' => ['required', 'in:P,A,L'],
        ]);

        $result = $api->post($request, '/api/attendance', $payload);

        if ($result['status'] !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.attendance.index', [], false))->with('success', 'Attendance created successfully.');
    }

    public function edit(Request $request, int $attendance, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/attendance/'.$attendance);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.attendance.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.attendance.form', [
            'mode' => 'edit',
            'item' => $result['data']['data'] ?? null,
            'userRole' => $request->user()->role,
        ] + $this->loadAcademicSelectOptions($request, $api, true, false, true));
    }

    public function update(Request $request, int $attendance, InternalApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'student_id' => ['required', 'integer'],
            'class_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['nullable', 'date_format:H:i'],
            'status' => ['required', 'in:P,A,L'],
        ]);

        $result = $api->put($request, '/api/attendance/'.$attendance, $payload);

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.attendance.index', [], false))->with('success', 'Attendance updated successfully.');
    }

    public function destroy(Request $request, int $attendance, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/attendance/'.$attendance);

        if ($result['status'] !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.attendance.index', [], false))->with('success', 'Attendance deleted successfully.');
    }
}
