@extends('web.layouts.app')

@section('content')
    @php
        $role = (string) ($userRole ?? '');
        $isStudent = $role === 'student';
        $isTeacherOrAdmin = in_array($role, ['super-admin', 'admin', 'teacher'], true);
        $homeworkId = (int) ($item['id'] ?? 0);
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
        <section class="panel panel-spaced">
            <div class="panel-head">Student Submissions</div>
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Submitted At</th>
                        <th>Answer</th>
                        <th>Files</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($item['submissions'] ?? [] as $submission)
                    @php
                        $submissionMedia = collect($submission['media'] ?? [])->filter(fn ($media) => ($media['category'] ?? '') === 'attachment')->values();
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
                    </tr>
                @empty
                    <tr><td colspan="4">No submission yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endif
@endsection
