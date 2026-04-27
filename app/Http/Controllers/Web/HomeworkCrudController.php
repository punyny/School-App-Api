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
        $filters = $request->validate([
            'student_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $api->get($request, '/api/homeworks/'.$homework);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.homeworks.index', [], false))->withErrors($this->extractErrors($result));
        }

        $item = $result['data']['data'] ?? [];
        $selectedStudentId = (int) ($filters['student_id'] ?? 0);

        $studentOptions = collect($item['statuses'] ?? [])
            ->map(function ($status): array {
                $studentId = (int) ($status['student_id'] ?? 0);
                $name = trim((string) ($status['student']['user']['name'] ?? ''));

                return [
                    'id' => $studentId,
                    'label' => $name !== '' ? $name : ('Student #'.$studentId),
                ];
            })
            ->merge(
                collect($item['submissions'] ?? [])->map(function ($submission): array {
                    $studentId = (int) ($submission['student_id'] ?? 0);
                    $name = trim((string) ($submission['student']['user']['name'] ?? ''));

                    return [
                        'id' => $studentId,
                        'label' => $name !== '' ? $name : ('Student #'.$studentId),
                    ];
                })
            )
            ->filter(fn (array $option): bool => (int) ($option['id'] ?? 0) > 0)
            ->unique('id')
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        if ($selectedStudentId > 0) {
            $item['submissions'] = collect($item['submissions'] ?? [])
                ->filter(fn ($submission): bool => (int) ($submission['student_id'] ?? 0) === $selectedStudentId)
                ->values()
                ->all();
        }

        return view('web.crud.homeworks.submission', [
            'item' => $item,
            'userRole' => $request->user()->normalizedRole(),
            'authStudentId' => (int) ($request->user()->studentProfile?->id ?? 0),
            'selectedStudentId' => $selectedStudentId,
            'studentOptions' => $studentOptions,
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

    public function gradeSubmission(Request $request, int $homework, int $submission, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validateGradePayload($request);
        $selectedStudentId = (int) $request->input('selected_student_id', 0);
        $result = $api->post(
            $request,
            "/api/homeworks/{$homework}/submissions/{$submission}/grade",
            $payload
        );

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        $routeParams = ['homework' => $homework];
        if ($selectedStudentId > 0) {
            $routeParams['student_id'] = $selectedStudentId;
        }

        return redirect()
            ->away(route('panel.homeworks.submission', $routeParams, false))
            ->with('success', 'បានដាក់ពិន្ទុកិច្ចការសិស្សរួចរាល់។');
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

    /**
     * @return array<string, mixed>
     */
    private function validateGradePayload(Request $request): array
    {
        $payload = $request->validate([
            'teacher_score' => ['required', 'numeric', 'min:0'],
            'teacher_score_max' => ['required', 'numeric', 'gt:0'],
            'score_weight_percent' => ['required', 'numeric', 'between:0,100'],
            'assessment_type' => ['required', 'in:monthly,semester'],
            'score_month' => ['nullable', 'integer', 'between:1,12', 'required_if:assessment_type,monthly'],
            'score_semester' => ['nullable', 'integer', 'between:1,2', 'required_if:assessment_type,semester'],
            'score_academic_year' => ['nullable', 'string', 'max:20'],
            'teacher_feedback' => ['nullable', 'string'],
        ]);

        $assessmentType = (string) ($payload['assessment_type'] ?? 'monthly');

        return [
            'teacher_score' => (float) $payload['teacher_score'],
            'teacher_score_max' => (float) $payload['teacher_score_max'],
            'score_weight_percent' => (float) $payload['score_weight_percent'],
            'assessment_type' => $assessmentType,
            'month' => $assessmentType === 'monthly' ? (int) ($payload['score_month'] ?? 0) : null,
            'semester' => $assessmentType === 'semester' ? (int) ($payload['score_semester'] ?? 0) : null,
            'academic_year' => (($year = trim((string) ($payload['score_academic_year'] ?? ''))) !== '') ? $year : null,
            'teacher_feedback' => (($feedback = trim((string) ($payload['teacher_feedback'] ?? ''))) !== '') ? $feedback : null,
        ];
    }
}
