<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $api->get($request, '/api/announcements', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.announcements.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
            'userRole' => $request->user()->role,
        ] + $this->loadAcademicSelectOptions($request, $api, needClasses: true));
    }

    public function create(Request $request, InternalApiClient $api): View
    {
        return view('web.crud.announcements.form', [
            'mode' => 'create',
            'item' => null,
            'userRole' => $request->user()->role,
            'targetUserOptions' => $this->loadTargetUserOptions($request, $api),
        ] + $this->loadAcademicSelectOptions($request, $api, needClasses: true));
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'school_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'target_role' => ['nullable', 'in:teacher,student,parent'],
            'target_user_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'date' => ['nullable', 'date'],
            'file_attachments' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ]);

        if ($request->user()->role !== 'super-admin') {
            unset($payload['school_id']);
        }

        if (($payload['class_id'] ?? null) === null || $payload['class_id'] === '') {
            $payload['class_id'] = null;
        }
        if (($payload['target_role'] ?? null) === null || $payload['target_role'] === '') {
            $payload['target_role'] = null;
        }
        if (($payload['target_user_id'] ?? null) === null || $payload['target_user_id'] === '') {
            $payload['target_user_id'] = null;
        }

        $payload['file_attachments'] = $this->parseAttachmentUrls($payload['file_attachments'] ?? null);
        if (! $request->hasFile('attachments')) {
            unset($payload['attachments']);
        } else {
            $payload['attachments'] = $request->file('attachments');
        }

        $result = $api->post($request, '/api/announcements', $payload);

        if ($result['status'] !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.announcements.index', [], false))->with('success', 'Announcement created successfully.');
    }

    public function edit(Request $request, int $announcement, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/announcements/'.$announcement);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.announcements.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.announcements.form', [
            'mode' => 'edit',
            'item' => $result['data']['data'] ?? null,
            'userRole' => $request->user()->role,
            'targetUserOptions' => $this->loadTargetUserOptions($request, $api),
        ] + $this->loadAcademicSelectOptions($request, $api, needClasses: true));
    }

    public function update(Request $request, int $announcement, InternalApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'school_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'target_role' => ['nullable', 'in:teacher,student,parent'],
            'target_user_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'date' => ['nullable', 'date'],
            'file_attachments' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ]);

        if ($request->user()->role !== 'super-admin') {
            unset($payload['school_id']);
        }

        if (($payload['class_id'] ?? null) === null || $payload['class_id'] === '') {
            $payload['class_id'] = null;
        }
        if (($payload['target_role'] ?? null) === null || $payload['target_role'] === '') {
            $payload['target_role'] = null;
        }
        if (($payload['target_user_id'] ?? null) === null || $payload['target_user_id'] === '') {
            $payload['target_user_id'] = null;
        }

        $payload['file_attachments'] = $this->parseAttachmentUrls($payload['file_attachments'] ?? null);
        if (! $request->hasFile('attachments')) {
            unset($payload['attachments']);
        } else {
            $payload['attachments'] = $request->file('attachments');
        }

        $result = $api->put($request, '/api/announcements/'.$announcement, $payload);

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.announcements.index', [], false))->with('success', 'Announcement updated successfully.');
    }

    public function destroy(Request $request, int $announcement, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/announcements/'.$announcement);

        if ($result['status'] !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.announcements.index', [], false))->with('success', 'Announcement deleted successfully.');
    }

    /**
     * @return array<int, string>
     */
    private function parseAttachmentUrls(?string $rawValue): array
    {
        $rawAttachments = trim((string) $rawValue);
        if ($rawAttachments === '') {
            return [];
        }

        return collect(explode(',', $rawAttachments))
            ->map(fn (string $url): string => trim($url))
            ->filter(fn (string $url): bool => $url !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id:int,label:string}>
     */
    private function loadTargetUserOptions(Request $request, InternalApiClient $api): array
    {
        if (! in_array((string) $request->user()->role, ['super-admin', 'admin'], true)) {
            return [];
        }

        $result = $api->get($request, '/api/users', ['per_page' => 100]);
        if (($result['status'] ?? 0) !== 200) {
            return [];
        }

        return collect($result['data']['data'] ?? [])
            ->filter(fn ($row): bool => is_array($row))
            ->map(function (array $row): array {
                $id = (int) ($row['id'] ?? 0);
                $role = strtolower(trim((string) ($row['role'] ?? '')));
                if ($role === 'guardian') {
                    $role = 'parent';
                }

                return [
                    'id' => $id,
                    'role' => $role,
                    'label' => trim((string) ($row['name'] ?? ('User '.$id))).' ('.$role.')',
                ];
            })
            ->filter(fn (array $row): bool => $row['id'] > 0 && in_array($row['role'], ['teacher', 'student', 'parent'], true))
            ->sortBy('label')
            ->map(fn (array $row): array => ['id' => $row['id'], 'label' => $row['label']])
            ->values()
            ->all();
    }
}
