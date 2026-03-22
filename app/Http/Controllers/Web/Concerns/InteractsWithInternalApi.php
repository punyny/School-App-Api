<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Services\InternalApiClient;
use Illuminate\Http\Request;

trait InteractsWithInternalApi
{
    private const SELECT_OPTIONS_PER_PAGE = 100;

    /**
     * @param  array{status:int, data:array<string,mixed>|null}  $result
     * @return array<string, array<int, string>>
     */
    protected function extractErrors(array $result): array
    {
        $payload = $result['data'] ?? [];

        if (isset($payload['errors']) && is_array($payload['errors'])) {
            /** @var array<string, array<int, string>> $errors */
            $errors = $payload['errors'];

            return $errors;
        }

        $message = is_array($payload) && isset($payload['message'])
            ? (string) $payload['message']
            : 'Request failed. Please try again.';

        return ['api' => [$message]];
    }

    /**
     * @return array{
     *     classOptions: array<int, array{id:int,label:string,school_id:int|null}>,
     *     subjectOptions: array<int, array{id:int,label:string}>,
     *     studentOptions: array<int, array{id:int,label:string,class_id:int|null,school_id:int|null}>
     * }
     */
    protected function loadAcademicSelectOptions(
        Request $request,
        InternalApiClient $api,
        bool $needClasses = false,
        bool $needSubjects = false,
        bool $needStudents = false
    ): array {
        $options = [
            'classOptions' => [],
            'subjectOptions' => [],
            'studentOptions' => [],
        ];

        /** @var array<int, array<string, mixed>> $classItems */
        $classItems = [];
        if ($needClasses || $needStudents) {
            $classItems = $this->fetchOptionItems($request, $api, '/api/classes');
        }

        if ($needClasses) {
            foreach ($classItems as $classItem) {
                $option = $this->formatClassOption($classItem);
                if ($option !== null) {
                    $options['classOptions'][] = $option;
                }
            }
        }

        if ($needSubjects) {
            $subjectItems = $this->fetchOptionItems($request, $api, '/api/subjects');

            if ($subjectItems !== []) {
                foreach ($subjectItems as $subjectItem) {
                    $option = $this->formatSubjectOption($subjectItem);
                    if ($option !== null) {
                        $options['subjectOptions'][] = $option;
                    }
                }
            } else {
                $options['subjectOptions'] = $this->buildSubjectOptionsFromClasses($request, $api, $classItems);
            }
        }

        if ($needStudents) {
            $options['studentOptions'] = $this->buildStudentOptions($request, $api, $classItems);
        }

        return $options;
    }

    /**
     * @return array<int, array{id:int,label:string}>
     */
    protected function loadSchoolSelectOptions(Request $request, InternalApiClient $api): array
    {
        $items = $this->fetchOptionItems($request, $api, '/api/schools');
        $options = [];

        foreach ($items as $item) {
            $option = $this->formatSchoolOption($item);
            if ($option !== null) {
                $options[] = $option;
            }
        }

        return $options;
    }

    /**
     * @return array<int, array{id:int,label:string,class_id:int|null,school_id:int|null}>
     */
    private function buildStudentOptions(
        Request $request,
        InternalApiClient $api,
        array $classItems
    ): array {
        $optionsById = [];

        $studentItems = $this->extractItems(
            $api->get($request, '/api/students', ['per_page' => self::SELECT_OPTIONS_PER_PAGE])
        );

        if ($studentItems === []) {
            $studentItems = $this->extractItems(
                $api->get($request, '/api/students')
            );
        }

        foreach ($studentItems as $studentItem) {
            $option = $this->formatStudentOption($studentItem, null, null);
            if ($option !== null) {
                $optionsById[$option['id']] = $option;
            }
        }

        if ($optionsById !== []) {
            return array_values($optionsById);
        }

        // Fallback: derive students from class details when /api/students is not available for current role.
        foreach (array_slice($classItems, 0, 60) as $classItem) {
            $classId = (int) ($classItem['id'] ?? 0);
            if ($classId <= 0) {
                continue;
            }

            $className = (string) ($classItem['name'] ?? '');
            $classResult = $api->get($request, '/api/classes/'.$classId);
            if (($classResult['status'] ?? 0) !== 200) {
                continue;
            }

            $classPayload = $classResult['data']['data'] ?? null;
            if (! is_array($classPayload)) {
                continue;
            }

            $students = $classPayload['students'] ?? [];
            if (! is_array($students)) {
                continue;
            }

            foreach ($students as $studentItem) {
                if (! is_array($studentItem)) {
                    continue;
                }

                $option = $this->formatStudentOption($studentItem, $className, $classId);
                if ($option !== null) {
                    $optionsById[$option['id']] = $option;
                }
            }
        }

        return array_values($optionsById);
    }

    /**
     * @param  array<int, array<string, mixed>>  $classItems
     * @return array<int, array{id:int,label:string}>
     */
    private function buildSubjectOptionsFromClasses(
        Request $request,
        InternalApiClient $api,
        array $classItems
    ): array {
        $optionsById = [];

        foreach (array_slice($classItems, 0, 60) as $classItem) {
            $classId = (int) ($classItem['id'] ?? 0);
            if ($classId <= 0) {
                continue;
            }

            $classResult = $api->get($request, '/api/classes/'.$classId);
            if (($classResult['status'] ?? 0) !== 200) {
                continue;
            }

            $classPayload = $classResult['data']['data'] ?? null;
            if (! is_array($classPayload)) {
                continue;
            }

            $subjects = $classPayload['subjects'] ?? [];
            if (! is_array($subjects)) {
                continue;
            }

            foreach ($subjects as $subjectItem) {
                if (! is_array($subjectItem)) {
                    continue;
                }

                $option = $this->formatSubjectOption($subjectItem);
                if ($option !== null) {
                    $optionsById[$option['id']] = $option;
                }
            }
        }

        return array_values($optionsById);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchOptionItems(Request $request, InternalApiClient $api, string $endpoint): array
    {
        $items = $this->extractItems(
            $api->get($request, $endpoint, ['per_page' => self::SELECT_OPTIONS_PER_PAGE])
        );

        if ($items !== []) {
            return $items;
        }

        return $this->extractItems($api->get($request, $endpoint));
    }

    /**
     * @param  array{status:int, data:array<string,mixed>|null}  $result
     * @return array<int, array<string, mixed>>
     */
    private function extractItems(array $result): array
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

        $normalized = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $classItem
     * @return array{id:int,label:string,school_id:int|null}|null
     */
    private function formatClassOption(array $classItem): ?array
    {
        $id = (int) ($classItem['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $name = trim((string) ($classItem['name'] ?? 'Class'));
        $grade = trim((string) ($classItem['grade_level'] ?? ''));
        $room = trim((string) ($classItem['room'] ?? ''));
        $label = $name !== '' ? $name : 'Class '.$id;

        if ($grade !== '') {
            $label .= ' ('.$grade.')';
        }

        if ($room !== '') {
            $label .= ' - '.$room;
        }

        $label .= ' - ID: '.$id;

        $schoolId = (int) ($classItem['school_id'] ?? 0);

        return [
            'id' => $id,
            'label' => $label,
            'school_id' => $schoolId > 0 ? $schoolId : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $schoolItem
     * @return array{id:int,label:string}|null
     */
    private function formatSchoolOption(array $schoolItem): ?array
    {
        $id = (int) ($schoolItem['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $name = trim((string) ($schoolItem['name'] ?? 'School'));
        $code = trim((string) ($schoolItem['school_code'] ?? ''));
        $location = trim((string) ($schoolItem['location'] ?? ''));

        $label = $name !== '' ? $name : 'School '.$id;

        if ($code !== '') {
            $label .= ' ('.$code.')';
        }

        if ($location !== '') {
            $label .= ' - '.$location;
        }

        $label .= ' - ID: '.$id;

        return ['id' => $id, 'label' => $label];
    }

    /**
     * @param  array<string, mixed>  $subjectItem
     * @return array{id:int,label:string}|null
     */
    private function formatSubjectOption(array $subjectItem): ?array
    {
        $id = (int) ($subjectItem['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $name = trim((string) ($subjectItem['name'] ?? 'Subject'));
        $label = ($name !== '' ? $name : 'Subject '.$id).' - ID: '.$id;

        return ['id' => $id, 'label' => $label];
    }

    /**
     * @param  array<string, mixed>  $studentItem
     * @return array{id:int,label:string,class_id:int|null,school_id:int|null}|null
     */
    private function formatStudentOption(array $studentItem, ?string $fallbackClassName, ?int $fallbackClassId): ?array
    {
        $id = (int) ($studentItem['id'] ?? $studentItem['student_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $name = trim((string) ($studentItem['user']['name'] ?? $studentItem['name'] ?? ''));
        $label = $name !== '' ? $name : 'Student '.$id;

        $className = trim((string) ($studentItem['class']['name'] ?? $fallbackClassName ?? ''));
        if ($className !== '') {
            $label .= ' ('.$className.')';
        }

        $classId = (int) ($studentItem['class_id'] ?? $studentItem['class']['id'] ?? $fallbackClassId ?? 0);
        $schoolId = (int) ($studentItem['user']['school_id'] ?? $studentItem['school_id'] ?? $studentItem['class']['school_id'] ?? 0);
        $label .= ' - ID: '.$id;

        return [
            'id' => $id,
            'label' => $label,
            'class_id' => $classId > 0 ? $classId : null,
            'school_id' => $schoolId > 0 ? $schoolId : null,
        ];
    }
}
