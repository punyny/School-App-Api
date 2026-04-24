<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class HomeworkCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $api->get($request, '/api/homeworks', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.homeworks.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
            'userRole' => $request->user()->normalizedRole(),
            'authStudentId' => (int) ($request->user()->studentProfile?->id ?? 0),
        ] + $this->loadAcademicSelectOptions($request, $api, true, true, false));
    }

    public function create(Request $request, InternalApiClient $api): View
    {
        return view('web.crud.homeworks.form', [
            'mode' => 'create',
            'item' => null,
        ] + $this->loadAcademicSelectOptions($request, $api, true, true, false));
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $result = $api->post($request, '/api/homeworks', $payload);

        if ($result['status'] !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.homeworks.index', [], false))->with('success', 'Homework created successfully.');
    }

    public function edit(Request $request, int $homework, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/homeworks/'.$homework);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.homeworks.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.homeworks.form', [
            'mode' => 'edit',
            'item' => $result['data']['data'] ?? null,
        ] + $this->loadAcademicSelectOptions($request, $api, true, true, false));
    }

    public function update(Request $request, int $homework, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $result = $api->put($request, '/api/homeworks/'.$homework, $payload);

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.homeworks.index', [], false))->with('success', 'Homework updated successfully.');
    }

    public function destroy(Request $request, int $homework, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/homeworks/'.$homework);

        if ($result['status'] !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.homeworks.index', [], false))->with('success', 'Homework deleted successfully.');
    }

    public function submission(Request $request, int $homework, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/homeworks/'.$homework);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.homeworks.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.homeworks.submission', [
            'item' => $result['data']['data'] ?? null,
            'userRole' => $request->user()->normalizedRole(),
            'authStudentId' => (int) ($request->user()->studentProfile?->id ?? 0),
        ]);
    }

    public function submitSubmission(Request $request, int $homework, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validateSubmissionPayload($request);
        $result = $api->post($request, '/api/homeworks/'.$homework.'/submissions', $payload);

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()
            ->away(route('panel.homeworks.submission', ['homework' => $homework], false))
            ->with('success', 'បានដាក់កិច្ចការទៅគ្រូជោគជ័យ។');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $payload = $request->validate([
            'class_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'question' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
            'file_attachments' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ]);

        $rawAttachments = trim((string) Arr::get($payload, 'file_attachments', ''));
        if ($rawAttachments === '') {
            $payload['file_attachments'] = [];
        } else {
            $payload['file_attachments'] = collect(explode(',', $rawAttachments))
                ->map(fn (string $url): string => trim($url))
                ->filter(fn (string $url): bool => $url !== '')
                ->values()
                ->all();
        }

        if (! $request->hasFile('attachments')) {
            unset($payload['attachments']);
        } else {
            $payload['attachments'] = $request->file('attachments');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSubmissionPayload(Request $request): array
    {
        $payload = $request->validate([
            'answer_text' => ['nullable', 'string'],
            'file_attachments' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ]);

        $rawAttachments = trim((string) Arr::get($payload, 'file_attachments', ''));
        if ($rawAttachments === '') {
            $payload['file_attachments'] = [];
        } else {
            $payload['file_attachments'] = collect(explode(',', $rawAttachments))
                ->map(fn (string $url): string => trim($url))
                ->filter(fn (string $url): bool => $url !== '')
                ->values()
                ->all();
        }

        if (! $request->hasFile('attachments')) {
            unset($payload['attachments']);
        } else {
            $payload['attachments'] = $request->file('attachments');
        }

        return $payload;
    }
}
