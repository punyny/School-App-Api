<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'read_status' => ['nullable', 'in:0,1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $api->get($request, '/api/notifications', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.notifications.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
        ] + $this->loadBroadcastOptions($request, $api));
    }

    public function create(): View
    {
        return view('web.crud.notifications.form', [
            'mode' => 'create',
            'item' => null,
        ]);
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request, true);
        $result = $api->post($request, '/api/notifications', $payload);

        if ($result['status'] !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.notifications.index', [], false))->with('success', 'Notification created successfully.');
    }

    public function edit(Request $request, int $notification, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/notifications/'.$notification);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.notifications.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.notifications.form', [
            'mode' => 'edit',
            'item' => $result['data']['data'] ?? null,
        ]);
    }

    public function update(Request $request, int $notification, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request, false);
        $result = $api->put($request, '/api/notifications/'.$notification, $payload);

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.notifications.index', [], false))->with('success', 'Notification updated successfully.');
    }

    public function destroy(Request $request, int $notification, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/notifications/'.$notification);

        if ($result['status'] !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.notifications.index', [], false))->with('success', 'Notification deleted successfully.');
    }

    public function broadcast(Request $request, InternalApiClient $api): RedirectResponse
    {
        $resolvedUserId = $request->input('user_id_manual');
        if ($resolvedUserId === null || $resolvedUserId === '') {
            $resolvedUserId = $request->input('user_id_select');
        }

        $request->merge([
            'user_id' => $resolvedUserId,
        ]);

        $payload = $request->validate([
            'audience' => ['required', 'in:teacher,all_teacher,student,all_student,class'],
            'user_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'send_at' => ['nullable', 'date'],
        ]);

        if (in_array($payload['audience'], ['teacher', 'student'], true) && empty($payload['user_id'])) {
            return back()->withInput()->withErrors([
                'user_id' => 'Please select a target user for this audience option.',
            ]);
        }

        if ($payload['audience'] === 'class' && empty($payload['class_id'])) {
            return back()->withInput()->withErrors([
                'class_id' => 'Please select a class for class broadcast.',
            ]);
        }

        $apiPayload = [
            'title' => $payload['title'],
            'content' => $payload['content'],
        ];

        if (! empty($payload['send_at'])) {
            $apiPayload['send_at'] = $payload['send_at'];
        }

        if ($payload['audience'] === 'teacher') {
            $apiPayload['user_ids'] = [(int) $payload['user_id']];
            $apiPayload['role'] = 'teacher';
        } elseif ($payload['audience'] === 'all_teacher') {
            $apiPayload['role'] = 'teacher';
        } elseif ($payload['audience'] === 'student') {
            $apiPayload['user_ids'] = [(int) $payload['user_id']];
            $apiPayload['role'] = 'student';
        } elseif ($payload['audience'] === 'all_student') {
            $apiPayload['role'] = 'student';
        } elseif ($payload['audience'] === 'class') {
            $apiPayload['class_id'] = (int) $payload['class_id'];
        }

        $result = $api->post($request, '/api/notifications/broadcast', $apiPayload);
        if (($result['status'] ?? 0) !== 202) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        $count = (int) ($result['data']['data']['recipients_count'] ?? 0);

        return redirect()->away(route('panel.notifications.index', [], false))
            ->with('success', 'Broadcast queued successfully for '.$count.' recipients.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $isCreate): array
    {
        $userRule = $isCreate ? ['required', 'integer'] : ['nullable', 'integer'];

        return $request->validate([
            'user_id' => $userRule,
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'date' => ['nullable', 'date'],
            'read_status' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array{
     *     classOptions: array<int, array{id:int,label:string}>,
     *     broadcastUserOptions: array<int, array{id:int,label:string,role:string}>
     * }
     */
    private function loadBroadcastOptions(Request $request, InternalApiClient $api): array
    {
        $classOptions = $this->loadAcademicSelectOptions($request, $api, true, false, false)['classOptions'];
        $broadcastUserOptions = [];

        $userResult = $api->get($request, '/api/users', [
            'per_page' => 100,
            'role' => 'teacher',
        ]);
        $studentResult = $api->get($request, '/api/users', [
            'per_page' => 100,
            'role' => 'student',
        ]);

        foreach ([$userResult, $studentResult] as $result) {
            if (($result['status'] ?? 0) !== 200 || ! is_array($result['data']['data'] ?? null)) {
                continue;
            }

            foreach ($result['data']['data'] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $id = (int) ($item['id'] ?? 0);
                $name = trim((string) ($item['name'] ?? ''));
                $role = trim((string) ($item['role'] ?? ''));
                if ($id <= 0 || $name === '' || ! in_array($role, ['teacher', 'student'], true)) {
                    continue;
                }

                $broadcastUserOptions[] = [
                    'id' => $id,
                    'label' => $name.' ('.$role.')',
                    'role' => $role,
                ];
            }
        }

        return [
            'classOptions' => $classOptions,
            'broadcastUserOptions' => $broadcastUserOptions,
        ];
    }
}
