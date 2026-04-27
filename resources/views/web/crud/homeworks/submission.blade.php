@extends('web.layouts.app')

@section('content')
    @php
        $role = (string) ($userRole ?? '');
        $isStudent = $role === 'student';
        $isTeacherOrAdmin = in_array($role, ['super-admin', 'admin', 'teacher'], true);
        $canGradeSubmissions = $isTeacherOrAdmin;
        $homeworkId = (int) ($item['id'] ?? 0);
        $selectedStudentId = (int) ($selectedStudentId ?? 0);
        $studentSelectOptions = collect($studentOptions ?? [])->values();
        $mySubmission = collect($item['submissions'] ?? [])
            ->first(fn ($submission) => (int) ($submission['student_id'] ?? 0) === (int) ($authStudentId ?? 0));
        $homeworkMedia = collect($item['media'] ?? [])->filter(fn ($media) => ($media['category'] ?? '') === 'attachment')->values();
    @endphp

    <h1 class="title">Homework Submission</h1>
    <p class="subtitle">Teacher can review student submissions. Student can submit text, PDF, image, and files.</p>

    <div class="nav">
        <a href="{{ route('panel.homeworks.index') }}">Back to homework list</a>
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <section class="panel panel-form panel-spaced">
        <div class="panel-head">Homework Detail</div>
        <div class="form-grid">
            <div>
                <label>Title</label>
                <p>{{ $item['title'] ?? '-' }}</p>
            </div>
            <div>
                <label>Class</label>
                <p>{{ $item['class']['class_name'] ?? $item['class']['name'] ?? ($item['class_id'] ?? '-') }}</p>
            </div>
            <div>
                <label>Subject</label>
                <p>{{ $item['subject']['name'] ?? ($item['subject_id'] ?? '-') }}</p>
            </div>
            <div>
                <label>Due Date</label>
                <p>{{ $item['due_date'] ?? '-' }}</p>
            </div>
        </div>

        <label>Question</label>
        <p>{{ $item['question'] ?? '-' }}</p>

        @if(collect($item['file_attachments'] ?? [])->isNotEmpty() || $homeworkMedia->isNotEmpty())
            <label>Homework Attachments</label>
            <div class="upload-hints">
                @foreach($item['file_attachments'] ?? [] as $fileUrl)
                    <a href="{{ $fileUrl }}" target="_blank" rel="noreferrer">{{ $fileUrl }}</a>
                @endforeach
                @foreach($homeworkMedia as $media)
                    <a href="{{ $media['url'] ?? '#' }}" target="_blank" rel="noreferrer">{{ $media['original_name'] ?? 'Attachment' }}</a>
                @endforeach
            </div>
        @endif
    </section>

    @if($isStudent)
        @php
            $myMedia = collect($mySubmission['media'] ?? [])->filter(fn ($media) => ($media['category'] ?? '') === 'attachment')->values();
        @endphp
        <form method="POST" action="{{ route('panel.homeworks.submit', $homeworkId) }}" class="panel panel-form panel-spaced" enctype="multipart/form-data">
            @csrf
            <div class="panel-head">{{ $mySubmission ? 'Update My Submission' : 'Submit My Homework' }}</div>

            <label>Answer Text</label>
            <textarea name="answer_text" rows="6">{{ old('answer_text', $mySubmission['answer_text'] ?? '') }}</textarea>

            <label>File Attachments (comma separated URLs)</label>
            <input type="text" name="file_attachments" value="{{ old('file_attachments', isset($mySubmission['file_attachments']) ? collect($mySubmission['file_attachments'])->implode(',') : '') }}">

            <label>Upload Files</label>
            <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">

            @if(collect($mySubmission['file_attachments'] ?? [])->isNotEmpty() || $myMedia->isNotEmpty())
                <label>Current Submission Files</label>
                <div class="upload-hints">
                    @foreach($mySubmission['file_attachments'] ?? [] as $fileUrl)
                        <a href="{{ $fileUrl }}" target="_blank" rel="noreferrer">{{ $fileUrl }}</a>
                    @endforeach
                    @foreach($myMedia as $media)
                        <a href="{{ $media['url'] ?? '#' }}" target="_blank" rel="noreferrer">{{ $media['original_name'] ?? 'Attachment' }}</a>
                    @endforeach
                </div>
            @endif

            <button type="submit" class="btn-space-top">Submit Homework</button>
        </form>
    @endif

    @if($isTeacherOrAdmin)
        <form method="GET" action="{{ route('panel.homeworks.submission', ['homework' => $homeworkId]) }}" class="panel panel-form panel-spaced">
            <div class="panel-head">Select Student</div>
            <div class="form-grid">
                <div>
                    <label>Student</label>
                    <select name="student_id">
                        <option value="">All students</option>
                        @foreach($studentSelectOptions as $studentOption)
                            @php
                                $optionId = (int) ($studentOption['id'] ?? 0);
                            @endphp
                            <option value="{{ $optionId }}" {{ $selectedStudentId === $optionId ? 'selected' : '' }}>
                                {{ $studentOption['label'] ?? ('Student #'.$optionId) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-space-top">Check Homework</button>
            @if($selectedStudentId > 0)
                <a href="{{ route('panel.homeworks.submission', ['homework' => $homeworkId]) }}">Reset Student Filter</a>
            @endif
        </form>

        <section class="panel panel-spaced">
            <div class="panel-head">Student Submissions</div>
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Submitted At</th>
                        <th>Answer</th>
                        <th>Files</th>
                        <th>Current Grade</th>
                        <th>Check</th>
                        @if($canGradeSubmissions)
                            <th>Teacher Grading</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                @forelse($item['submissions'] ?? [] as $submission)
                    @php
                        $submissionId = (int) ($submission['id'] ?? 0);
                        $submissionMedia = collect($submission['media'] ?? [])->filter(fn ($media) => ($media['category'] ?? '') === 'attachment')->values();
                        $teacherScore = is_numeric($submission['teacher_score'] ?? null) ? (float) $submission['teacher_score'] : null;
                        $teacherScoreMax = is_numeric($submission['teacher_score_max'] ?? null) ? (float) $submission['teacher_score_max'] : null;
                        $weightPercent = is_numeric($submission['score_weight_percent'] ?? null) ? (float) $submission['score_weight_percent'] : null;
                        $rawPercent = ($teacherScore !== null && $teacherScoreMax !== null && $teacherScoreMax > 0)
                            ? round(($teacherScore / $teacherScoreMax) * 100, 2)
                            : null;
                        $weightedPercent = ($rawPercent !== null && $weightPercent !== null)
                            ? round(($rawPercent * $weightPercent) / 100, 2)
                            : null;
                        $assessmentType = old('assessment_type', $submission['score_assessment_type'] ?? 'monthly');
                        $currentMonth = old('score_month', $submission['score_month'] ?? now()->month);
                        $currentSemester = old('score_semester', $submission['score_semester'] ?? 1);
                        $currentAcademicYear = old('score_academic_year', $submission['score_academic_year'] ?? (string) now()->year);
                        $currentTeacherScore = old('teacher_score', $submission['teacher_score'] ?? '');
                        $currentTeacherScoreMax = old('teacher_score_max', $submission['teacher_score_max'] ?? 100);
                        $currentWeightPercent = old('score_weight_percent', $submission['score_weight_percent'] ?? 100);
                        $currentFeedback = old('teacher_feedback', $submission['teacher_feedback'] ?? '');
                        $isChecked = ! empty($submission['graded_at']);
                    @endphp
                    <tr>
                        <td>{{ $submission['student']['user']['name'] ?? ($submission['student_id'] ?? '-') }}</td>
                        <td>{{ $submission['submitted_at'] ?? '-' }}</td>
                        <td>{{ $submission['answer_text'] ?? '-' }}</td>
                        <td>
                            <div class="upload-hints">
                                @foreach($submission['file_attachments'] ?? [] as $fileUrl)
                                    <a href="{{ $fileUrl }}" target="_blank" rel="noreferrer">File URL</a>
                                @endforeach
                                @foreach($submissionMedia as $media)
                                    <a href="{{ $media['url'] ?? '#' }}" target="_blank" rel="noreferrer">{{ $media['original_name'] ?? 'Attachment' }}</a>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            @if($teacherScore !== null && $teacherScoreMax !== null)
                                <div>{{ number_format($teacherScore, 2) }} / {{ number_format($teacherScoreMax, 2) }}</div>
                                <div>Weight: {{ number_format((float) ($weightPercent ?? 0), 2) }}%</div>
                                <div>Raw: {{ number_format((float) ($rawPercent ?? 0), 2) }}%</div>
                                <div>Weighted: {{ number_format((float) ($weightedPercent ?? 0), 2) }}%</div>
                                <div>
                                    {{ ucfirst((string) ($submission['score_assessment_type'] ?? 'monthly')) }}
                                    @if(($submission['score_assessment_type'] ?? 'monthly') === 'monthly' && ! empty($submission['score_month']))
                                        (Month {{ $submission['score_month'] }})
                                    @elseif(($submission['score_assessment_type'] ?? '') === 'semester' && ! empty($submission['score_semester']))
                                        (Semester {{ $submission['score_semester'] }})
                                    @endif
                                </div>
                                <div>Year: {{ $submission['score_academic_year'] ?? '-' }}</div>
                                <div>Graded By: {{ $submission['graded_by']['name'] ?? '-' }}</div>
                                <div>Graded At: {{ $submission['graded_at'] ?? '-' }}</div>
                                <div>{{ $submission['teacher_feedback'] ?? '' }}</div>
                            @else
                                <span>-</span>
                            @endif
                        </td>
                        <td>{{ $isChecked ? 'Checked' : 'Not Checked' }}</td>
                        @if($canGradeSubmissions)
                            <td>
                                <form method="POST" action="{{ route('panel.homeworks.grade', ['homework' => $homeworkId, 'submission' => $submissionId]) }}" class="panel panel-form" style="padding: 12px; min-width: 310px;">
                                    @csrf
                                    <input type="hidden" name="selected_student_id" value="{{ $selectedStudentId }}">
                                    <div class="form-grid">
                                        <div>
                                            <label>Score</label>
                                            <input type="number" name="teacher_score" step="0.01" min="0" value="{{ $currentTeacherScore }}" required>
                                        </div>
                                        <div>
                                            <label>Max Score</label>
                                            <input type="number" name="teacher_score_max" step="0.01" min="0.01" value="{{ $currentTeacherScoreMax }}" required>
                                        </div>
                                        <div>
                                            <label>Weight %</label>
                                            <input type="number" name="score_weight_percent" step="0.01" min="0" max="100" value="{{ $currentWeightPercent }}" required>
                                        </div>
                                        <div>
                                            <label>Assessment</label>
                                            <select name="assessment_type" required>
                                                <option value="monthly" {{ $assessmentType === 'monthly' ? 'selected' : '' }}>Monthly</option>
                                                <option value="semester" {{ $assessmentType === 'semester' ? 'selected' : '' }}>Semester</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label>Month (1-12)</label>
                                            <input type="number" name="score_month" min="1" max="12" value="{{ $currentMonth }}">
                                        </div>
                                        <div>
                                            <label>Semester (1-2)</label>
                                            <input type="number" name="score_semester" min="1" max="2" value="{{ $currentSemester }}">
                                        </div>
                                        <div>
                                            <label>Academic Year</label>
                                            <input type="text" name="score_academic_year" value="{{ $currentAcademicYear }}" placeholder="2026">
                                        </div>
                                    </div>
                                    <label>Feedback</label>
                                    <textarea name="teacher_feedback" rows="2">{{ $currentFeedback }}</textarea>
                                    <button type="submit" class="btn-space-top">Check & Save Score</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canGradeSubmissions ? 7 : 6 }}">No submission yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endif
@endsection
