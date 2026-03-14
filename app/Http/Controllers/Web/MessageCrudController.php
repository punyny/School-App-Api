<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'sender_id' => ['nullable', 'integer'],
            'receiver_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $api->get($request, '/api/messages', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.messages.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
            'authUserId' => (int) ($request->user()->id ?? 0),
            'userRole' => $this->normalizedRole($request),
            'canCreateMessage' => $request->user()->can('web-create-messages'),
        ] + $this->loadAcademicSelectOptions($request, $api, true, false, false));
    }

    public function create(Request $request, InternalApiClient $api): View
    {
        $role = $this->normalizedRole($request);

        return view('web.crud.messages.form', [
            'mode' => 'create',
            'item' => null,
            'receiverOptions' => $this->buildReceiverOptions($request, $api),
            'canClassBroadcast' => in_array($role, ['super-admin', 'admin', 'teacher'], true),
            'userRole' => $role,
        ] + $this->loadAcademicSelectOptions($request, $api, in_array($role, ['super-admin', 'admin', 'teacher'], true), false, false));
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $result = $api->post($request, '/api/messages', $payload);

        if ($result['status'] !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.messages.index', [], false))->with('success', 'Message created successfully.');
    }

    public function show(Request $request, int $message, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/messages/'.$message);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.messages.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.messages.show', [
            'item' => $result['data']['data'] ?? null,
            'authUserId' => (int) ($request->user()->id ?? 0),
            'userRole' => $this->normalizedRole($request),
            'canCreateMessage' => $request->user()->can('web-create-messages'),
        ]);
    }

    /**
     * @return array<int, array{id:int,label:string}>
     */
    private function buildReceiverOptions(Request $request, InternalApiClient $api): array
    {
        $role = $this->normalizedRole($request);
        $currentUserId = (int) ($request->user()->id ?? 0);

        if (in_array($role, ['super-admin', 'admin'], true)) {
            $result = $api->get($request, '/api/users', ['per_page' => 100]);
            if (($result['status'] ?? 0) !== 200) {
                return [];
            }

            return collect($result['data']['data'] ?? [])
                ->filter(fn ($item): bool => is_array($item))
                ->map(function (array $item): array {
                    $id = (int) ($item['id'] ?? 0);
                    $roleLabel = strtolower(trim((string) ($item['role'] ?? '')));
                    if ($roleLabel === 'guardian') {
                        $roleLabel = 'parent';
                    }

                    return [
                        'id' => $id,
                        'role' => $roleLabel,
                        'label' => trim((string) ($item['name'] ?? ('User '.$id))).' ('.$roleLabel.')',
                    ];
                })
                ->filter(fn (array $row): bool => $row['id'] > 0 && in_array($row['role'], ['teacher', 'student', 'parent'], true))
                ->sortBy('label')
                ->map(fn (array $row): array => ['id' => $row['id'], 'label' => $row['label']])
                ->values()
                ->all();
        }

        if ($role === 'teacher') {
            $classesResult = $api->get($request, '/api/classes', ['per_page' => 60]);
            if (($classesResult['status'] ?? 0) !== 200) {
                return [];
            }

            $receivers = [];
            foreach ((array) ($classesResult['data']['data'] ?? []) as $classItem) {
                if (! is_array($classItem)) {
                    continue;
                }

                $classId = (int) ($classItem['id'] ?? 0);
                if ($classId <= 0) {
                    continue;
                }

                $classResult = $api->get($request, '/api/classes/'.$classId);
                if (($classResult['status'] ?? 0) !== 200 || ! is_array($classResult['data']['data'] ?? null)) {
                    continue;
                }

                $classData = $classResult['data']['data'];
                $className = trim((string) ($classData['name'] ?? ('Class '.$classId)));
                foreach ((array) ($classData['students'] ?? []) as $student) {
                    if (! is_array($student)) {
                        continue;
                    }

                    $receiverId = (int) ($student['user']['id'] ?? 0);
                    if ($receiverId <= 0 || $receiverId === $currentUserId) {
                        continue;
                    }

                    $receivers[$receiverId] = [
                        'id' => $receiverId,
                        'label' => trim((string) ($student['user']['name'] ?? ('Student '.$receiverId))).' (student / '.$className.')',
                    ];
                }
            }

            return collect($receivers)
                ->sortBy('label')
                ->values()
                ->all();
        }

        if ($role === 'student') {
            $meResult = $api->get($request, '/api/auth/me');
            if (($meResult['status'] ?? 0) !== 200) {
                return [];
            }

            $classId = (int) ($meResult['data']['user']['student_profile']['class_id'] ?? 0);
            if ($classId <= 0) {
                return [];
            }

            $classResult = $api->get($request, '/api/classes/'.$classId);
            if (($classResult['status'] ?? 0) !== 200 || ! is_array($classResult['data']['data'] ?? null)) {
                return [];
            }

            return collect((array) ($classResult['data']['data']['teachers'] ?? []))
                ->filter(fn ($teacher): bool => is_array($teacher))
                ->map(function (array $teacher): array {
                    $id = (int) ($teacher['id'] ?? 0);

                    return [
                        'id' => $id,
                        'label' => trim((string) ($teacher['name'] ?? ('Teacher '.$id))).' (teacher)',
                    ];
                })
                ->filter(fn (array $row): bool => $row['id'] > 0)
                ->sortBy('label')
                ->values()
                ->all();
        }

        return [];
    }

    private function normalizedRole(Request $request): string
    {
        $role = strtolower(trim((string) ($request->user()->role ?? '')));

        return $role === 'guardian' ? 'parent' : $role;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $payload = $request->validate([
            'receiver_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'content' => ['required', 'string'],
            'date' => ['nullable', 'date'],
        ]);

        if (($payload['receiver_id'] ?? null) === null && ($payload['class_id'] ?? null) === null) {
            return $payload;
        }

        if (($payload['receiver_id'] ?? null) !== null && ($payload['class_id'] ?? null) !== null) {
            // Keep web validation aligned with API: one target only (direct or class).
            return throw \Illuminate\Validation\ValidationException::withMessages([
                'receiver_id' => ['Use one target only: direct receiver or class.'],
                'class_id' => ['Use one target only: direct receiver or class.'],
            ]);
        }

        return $payload;
    }
}
