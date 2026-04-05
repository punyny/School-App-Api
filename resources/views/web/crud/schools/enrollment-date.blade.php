@extends('web.layouts.app')

@section('content')
    <h1 class="title">{{ __('ui.layout.enrollment_date') }}</h1>
    <p class="subtitle">Set one enrollment date for the whole school.</p>

    <div class="nav">
        <a href="{{ route('panel.schools.index') }}">{{ __('ui.layout.school_directory') }}</a>
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    @if(($canChooseSchool ?? false) === true)
        <section class="panel panel-form">
            <form method="GET" action="{{ route('panel.schools.enrollment-date') }}">
                <label for="school_id">{{ __('ui.layout.school_directory') }}</label>
                <select id="school_id" name="school_id" required>
                    <option value="">Select school</option>
                    @foreach(($schoolOptions ?? []) as $option)
                        <option value="{{ $option['id'] }}" {{ (int)($schoolId ?? 0) === (int)$option['id'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn-space-top">Open Enrollment Date</button>
            </form>
        </section>
    @endif

    @if(($schoolMissing ?? false) === true)
        <section class="panel panel-form">
            <p class="flash-error">Your admin account has no school assigned yet. Please ask super-admin to assign your school first.</p>
        </section>
    @elseif(($selectionRequired ?? false) === true)
        <section class="panel panel-form">
            <p class="text-muted">Select one school above to edit Enrollment Date.</p>
        </section>
    @else
        <section class="panel panel-form">
            <p class="text-muted">
                School:
                <strong>{{ $school['name'] ?? 'N/A' }}</strong>
                @if(!empty($school['school_code']))
                    ({{ $school['school_code'] }})
                @endif
            </p>

            <form method="POST" action="{{ route('panel.schools.enrollment-date.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="school_id" value="{{ $schoolId }}">

                <label for="default_enrollment_date">Default Enrollment Date (School-Wide)</label>
                <input
                    id="default_enrollment_date"
                    type="date"
                    name="default_enrollment_date"
                    value="{{ old('default_enrollment_date', $defaultEnrollmentDate ?? '') }}"
                >
                <p class="text-muted">Attendance rules will use this date as the default start date for all students in this school.</p>

                <button type="submit" class="btn-space-top">Save Enrollment Date</button>
            </form>
        </section>
    @endif
@endsection
