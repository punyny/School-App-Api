@extends('web.layouts.app')

@section('content')
    @php
        $roleLabels = [
            'super-admin' => 'Super Admin',
            'admin' => 'Admin',
            'teacher' => 'Teacher',
            'student' => 'Student',
            'parent' => 'Parent',
            'guardian' => 'Parent',
        ];

        $user = is_array($userData ?? null) ? $userData : [];
        $role = strtolower((string) ($user['role'] ?? ''));
        if ($role === 'guardian') {
            $role = 'parent';
        }

        $insight = is_array($insights ?? null) ? $insights : [];

        $teacherClasses = collect($insight['classes'] ?? [])->filter(fn ($item) => is_array($item))->values();
        $teacherStudents = collect($insight['students'] ?? [])->filter(fn ($item) => is_array($item))->values();
        $teacherTimetable = collect($insight['timetable'] ?? [])->filter(fn ($item) => is_array($item))->values();

        $studentParents = collect($insight['parents'] ?? [])->filter(fn ($item) => is_array($item))->values();
        $studentSubjects = collect($insight['subjects'] ?? [])->filter(fn ($item) => is_array($item))->values();
        $studentScores = collect($insight['recent_scores'] ?? [])->filter(fn ($item) => is_array($item))->values();

        $parentChildren = collect($insight['children'] ?? [])->filter(fn ($item) => is_array($item))->values();

        $roleStats = [];
        if ($role === 'teacher') {
            $roleStats = [
                ['label' => 'My Classes', 'value' => (int) ($insight['stats']['classes_count'] ?? $teacherClasses->count())],
                ['label' => 'My Students', 'value' => (int) ($insight['stats']['students_count'] ?? $teacherStudents->count())],
                ['label' => 'My Timetable Rows', 'value' => (int) ($insight['stats']['timetable_count'] ?? $teacherTimetable->count())],
            ];
        } elseif ($role === 'student') {
            $roleStats = [
                ['label' => 'My Subjects', 'value' => (int) ($insight['stats']['subjects_count'] ?? $studentSubjects->count())],
                ['label' => 'Recent Scores', 'value' => (int) ($insight['stats']['scores_count'] ?? $studentScores->count())],
                ['label' => 'Parents Linked', 'value' => $studentParents->count()],
            ];
        } elseif ($role === 'parent') {
            $roleStats = [
                ['label' => 'My Children', 'value' => (int) ($insight['stats']['children_count'] ?? $parentChildren->count())],
                ['label' => 'Children With Scores', 'value' => (int) ($insight['stats']['with_score_count'] ?? 0)],
                ['label' => 'Linked Profiles', 'value' => count(is_array($user['children'] ?? null) ? $user['children'] : [])],
            ];
        }

        $subtitle = match ($role) {
            'teacher' => 'My class, timetable, and students in one view.',
            'student' => 'My information, class, grade, and recent results.',
            'parent' => 'My profile and children information in one easy view.',
            default => 'Manage your personal information and profile photo.',
        };

        $payload = [
            'role' => $role,
            'user' => [
                'name' => (string) ($user['name'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'phone' => (string) ($user['phone'] ?? ''),
                'address' => (string) ($user['address'] ?? ''),
                'bio' => (string) ($user['bio'] ?? ''),
                'image_url' => old('image_url', (string) ($user['image_url'] ?? '')),
                'telegram_chat_id' => (string) ($user['telegram_chat_id'] ?? ''),
                'role_label' => (string) ($roleLabels[$role] ?? ucfirst($role !== '' ? $role : 'user')),
            ],
            'insights' => $insight,
            'roleStats' => $roleStats,
            'profileForm' => [
                'name' => old('name', (string) ($user['name'] ?? '')),
                'phone' => old('phone', (string) ($user['phone'] ?? '')),
                'address' => old('address', (string) ($user['address'] ?? '')),
                'bio' => old('bio', (string) ($user['bio'] ?? '')),
                'image_url' => old('image_url', (string) ($user['image_url'] ?? '')),
                'remove_image' => (bool) old('remove_image', false),
            ],
            'flashes' => [
                'success' => (string) session('success', ''),
                'error' => $errors->any() ? (string) $errors->first() : '',
            ],
            'csrfToken' => csrf_token(),
            'endpoints' => [
                'update' => route('profile.update'),
                'changePassword' => route('profile.change-password'),
                'telegramLinkCode' => route('profile.telegram.link-code'),
            ],
        ];
    @endphp

    <div class="topbar">
        <div>
            <h1 class="title">My Profile</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <div class="mini-actions">
            <a href="{{ route('dashboard') }}">Dashboard</a>
            <a href="#react-profile-root">Edit Profile</a>
        </div>
    </div>

    <div id="react-profile-root"></div>

    <script>
        window.__PROFILE_PAGE__ = @json($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    </script>
@endsection

@push('scripts')
    @vite('resources/js/react-profile.js')
@endpush
