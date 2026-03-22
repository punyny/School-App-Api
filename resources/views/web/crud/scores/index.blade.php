@extends('web.layouts.app')

@section('content')
    <h1 class="title">Score Management</h1>
    <p class="subtitle">Input scores by class, subject, and assessment period while tracking student ranking.</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        @can('web-manage-scores')
        <a href="{{ route('panel.scores.create') }}" class="active">+ Create Score</a>
        @endcan
        
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    @php
        $selectedClassId = (string) ($filters['class_id'] ?? '');
        $selectedStudentId = (string) ($filters['student_id'] ?? '');
        $selectedSubjectId = (string) ($filters['subject_id'] ?? '');
        $classSelectOptions = collect($classOptions ?? []);
        $studentSelectOptions = collect($studentOptions ?? []);
        $subjectSelectOptions = collect($subjectOptions ?? []);
        $itemsCollection = collect($items ?? [])->filter(fn ($row): bool => is_array($row))->values();
        $khmerMonths = \App\Support\KhmerMonth::options();

        if ($selectedClassId !== '' && ! $classSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedClassId)) {
            $classSelectOptions = $classSelectOptions->prepend([
                'id' => (int) $selectedClassId,
                'label' => 'Class ID: '.$selectedClassId,
            ]);
        }

        if ($selectedStudentId !== '' && ! $studentSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedStudentId)) {
            $studentSelectOptions = $studentSelectOptions->prepend([
                'id' => (int) $selectedStudentId,
                'label' => 'Student ID: '.$selectedStudentId,
            ]);
        }

        if ($selectedSubjectId !== '' && ! $subjectSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedSubjectId)) {
            $subjectSelectOptions = $subjectSelectOptions->prepend([
                'id' => (int) $selectedSubjectId,
                'label' => 'Subject ID: '.$selectedSubjectId,
            ]);
        }

        $matrixStudents = $itemsCollection
            ->map(fn (array $item): array => [
                'id' => (int) ($item['student_id'] ?? 0),
                'name' => (string) ($item['student']['user']['name'] ?? ('Student '.($item['student_id'] ?? ''))),
            ])
            ->filter(fn (array $student): bool => $student['id'] > 0)
            ->unique('id')
            ->sortBy('name')
            ->values();

        $matrixSubjects = $itemsCollection
            ->map(fn (array $item): array => [
                'id' => (int) ($item['subject_id'] ?? 0),
                'name' => (string) ($item['subject']['name'] ?? ('Subject '.($item['subject_id'] ?? ''))),
            ])
            ->filter(fn (array $subject): bool => $subject['id'] > 0)
            ->unique('id')
            ->sortBy('name')
            ->values();

        $matrixData = [];
        foreach ($itemsCollection as $item) {
            $studentId = (int) ($item['student_id'] ?? 0);
            $subjectId = (int) ($item['subject_id'] ?? 0);
            if ($studentId <= 0 || $subjectId <= 0) {
                continue;
            }

            $currentId = (int) ($item['id'] ?? 0);
            $existingId = (int) ($matrixData[$studentId][$subjectId]['id'] ?? 0);
            if ($existingId > $currentId) {
                continue;
            }

            $matrixData[$studentId][$subjectId] = [
                'id' => $currentId,
                'score' => $item['total_score'] ?? '',
            ];
        }

    @endphp

    <form method="GET" action="{{ route('panel.scores.index') }}" class="panel panel-form panel-spaced">
        <div class="form-grid">
            <div>
                <input type="text" class="searchable-select-search" placeholder="Search class..." data-select-search-for="filter_class_id">
                <select id="filter_class_id" name="class_id">
                    <option value="">Class</option>
                    @foreach($classSelectOptions as $option)
                        <option value="{{ $option['id'] }}" {{ $selectedClassId === (string) $option['id'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <input type="text" class="searchable-select-search" placeholder="Search student..." data-select-search-for="filter_student_id">
                <select id="filter_student_id" name="student_id">
                    <option value="">Student</option>
                    @foreach($studentSelectOptions as $option)
                        <option value="{{ $option['id'] }}" {{ $selectedStudentId === (string) $option['id'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <input type="text" class="searchable-select-search" placeholder="Search subject..." data-select-search-for="filter_subject_id">
                <select id="filter_subject_id" name="subject_id">
                    <option value="">Subject</option>
                    @foreach($subjectSelectOptions as $option)
                        <option value="{{ $option['id'] }}" {{ $selectedSubjectId === (string) $option['id'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <select name="assessment_type">
                <option value="">Assessment Type</option>
                <option value="monthly" {{ ($filters['assessment_type'] ?? '') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                <option value="semester" {{ ($filters['assessment_type'] ?? '') === 'semester' ? 'selected' : '' }}>Semester</option>
                <option value="yearly" {{ ($filters['assessment_type'] ?? '') === 'yearly' ? 'selected' : '' }}>Yearly</option>
            </select>
            <select name="month">
                <option value="">Month</option>
                @foreach($khmerMonths as $monthNumber => $monthName)
                    <option value="{{ $monthNumber }}" {{ (string) ($filters['month'] ?? '') === (string) $monthNumber ? 'selected' : '' }}>
                        {{ $monthName }}
                    </option>
                @endforeach
            </select>
            <input type="number" name="semester" placeholder="Semester" min="1" max="2" value="{{ $filters['semester'] ?? '' }}">
            <input type="text" name="academic_year" placeholder="Academic Year" value="{{ $filters['academic_year'] ?? '' }}">
            <input type="number" name="quarter" placeholder="Quarter" value="{{ $filters['quarter'] ?? '' }}">
            <input type="text" name="period" placeholder="Period" value="{{ $filters['period'] ?? '' }}">
            <input type="number" name="rank_in_class" placeholder="Rank" min="1" value="{{ $filters['rank_in_class'] ?? '' }}">
        </div>
        <button type="submit" class="btn-space-top">Filter</button>
    </form>

    <section class="panel">
        <div class="panel-head">Scores (Detailed Records)</div>
        <div class="score-matrix-wrap">
            <table class="score-matrix">
                <thead>
                    <tr>
                        <th class="score-matrix-student">ឈ្មោះសិស្ស</th>
                        @foreach($matrixSubjects as $subject)
                            <th class="score-matrix-subject">{{ $subject['name'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($matrixStudents as $student)
                        <tr>
                            <td class="score-matrix-student"><strong>{{ $student['name'] }}</strong></td>
                            @foreach($matrixSubjects as $subject)
                                @php
                                    $cell = $matrixData[(int) $student['id']][(int) $subject['id']] ?? null;
                                @endphp
                                <td>
                                    <input
                                        class="score-matrix-input"
                                        type="text"
                                        value="{{ $cell['score'] ?? '' }}"
                                        placeholder="-"
                                        readonly
                                    >
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ max(1, $matrixSubjects->count() + 1) }}">No data.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
