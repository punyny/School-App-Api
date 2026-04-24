<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubstituteAssignmentCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'substitute_teacher_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (empty($filters['date'])) {
            $filters['date'] = now()->toDateString();
        }

        $result = $api->get(
            $request,
            '/api/substitute-assignments',
            array_filter($filters, fn ($value) => $value !== null && $value !== '')
        );

        if (($result['status'] ?? 0) !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.substitute_assignments.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
            'teacherOptions' => $this->loadTeacherOptions($request, $api),
        ] + $this->loadSubstituteFormOptions($request, $api));
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'class_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'substitute_teacher_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i', 'after:time_start'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $api->post($request, '/api/substitute-assignments', $payload);
        if (($result['status'] ?? 0) !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()
            ->away(route('panel.substitute-assignments.index', ['date' => $payload['date']], false))
            ->with('success', (string) ($result['data']['message'] ?? 'Substitute teacher assigned successfully.'));
    }

    public function destroy(Request $request, int $substituteAssignment, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/substitute-assignments/'.$substituteAssignment);
        if (($result['status'] ?? 0) !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return back()->with('success', (string) ($result['data']['message'] ?? 'Substitute assignment removed successfully.'));
    }

    /**
     * @return array{
     *     classOptions: array<int, array{id:int,label:string,school_id:int|null}>,
     *     subjectOptions: array<int, array{id:int,label:string,class_ids:array<int,int>}>,
     *     subjectOptionsByClass: array<string, array<int, array{id:int,label:string}>>
     * }
     */
    private function loadSubstituteFormOptions(Request $request, InternalApiClient $api): array
    {
        $options = $this->loadAcademicSelectOptions($request, $api, true, false, false);
        $subjectOptionsByClass = [];
        $subjectOptionsById = [];

        foreach ($options['classOptions'] as $classOption) {
            $classId = (int) ($classOption['id'] ?? 0);
            if ($classId <= 0) {
                continue;
            }

            $subjectResult = $api->get($request, '/api/subjects', [
                'class_id' => $classId,
                'per_page' => 100,
            ]);

            $subjectItems = $this->extractOptionItems($subjectResult);
            foreach ($subjectItems as $subjectItem) {
                $subjectId = (int) ($subjectItem['id'] ?? 0);
                if ($subjectId <= 0) {
                    continue;
                }

                $label = trim((string) ($subjectItem['name'] ?? 'Subject'));
                $option = [
                    'id' => $subjectId,
                    'label' => ($label !== '' ? $label : 'Subject '.$subjectId).' - ID: '.$subjectId,
                ];

                $subjectOptionsByClass[(string) $classId] ??= [];
                $subjectOptionsByClass[(string) $classId][$subjectId] = $option;

                if (! isset($subjectOptionsById[$subjectId])) {
                    $subjectOptionsById[$subjectId] = $option + ['class_ids' => [$classId]];
                    continue;
                }

                $subjectOptionsById[$subjectId]['class_ids'][] = $classId;
                $subjectOptionsById[$subjectId]['class_ids'] = array_values(
                    array_unique($subjectOptionsById[$subjectId]['class_ids'])
                );
            }
        }

        $options['subjectOptions'] = array_values($subjectOptionsById);
        $options['subjectOptionsByClass'] = array_map(
            fn (array $rows): array => array_values($rows),
            $subjectOptionsByClass
        );

        return $options;
    }

    /**
     * @return array<int, array{id:int,label:string}>
     */
    private function loadTeacherOptions(Request $request, InternalApiClient $api): array
    {
        $result = $api->get($request, '/api/users', [
            'role' => 'teacher',
            'per_page' => 100,
        ]);

        if (($result['status'] ?? 0) !== 200) {
            return [];
        }

        $rows = $result['data']['data'] ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? 'Teacher'));
            $code = trim((string) ($row['user_code'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));

            $label = $name !== '' ? $name : 'Teacher '.$id;
            if ($code !== '') {
                $label .= ' ('.$code.')';
            }
            if ($email !== '') {
                $label .= ' - '.$email;
            }
            $label .= ' - ID: '.$id;

            $options[] = ['id' => $id, 'label' => $label];
        }

        return $options;
    }

    /**
     * @param  array{status:int, data:array<string,mixed>|null}  $result
     * @return array<int, array<string, mixed>>
     */
    private function extractOptionItems(array $result): array
    {
        if (($result['status'] ?? 0) !== 200) {
            return [];
        }

        $payload = $result['data'] ?? [];
        if (! is_array($payload)) {
            return [];
        }

        $items = $payload['data'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, fn (mixed $item): bool => is_array($item)));
    }
}
