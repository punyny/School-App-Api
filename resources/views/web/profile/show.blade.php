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

        $resolveImage = static function (?string $value): string {
            $url = trim((string) $value);
            if ($url === '') {
                return '';
            }

            if (\Illuminate\Support\Str::startsWith($url, ['http://', 'https://', '/', 'data:'])) {
                return $url;
            }

            return asset($url);
        };

        $user = is_array($userData ?? null) ? $userData : [];
        $role = strtolower((string) ($user['role'] ?? ''));
        if ($role === 'guardian') {
            $role = 'parent';
        }

        $insight = is_array($insights ?? null) ? $insights : [];
        $studentProfile = is_array($user['student_profile'] ?? null) ? $user['student_profile'] : null;
        $children = is_array($user['children'] ?? null) ? $user['children'] : [];
        $currentImage = old('image_url', $user['image_url'] ?? '');
        $avatarText = mb_strtoupper(mb_substr((string) ($user['name'] ?? 'U'), 0, 1));

        $teacherClasses = collect($insight['classes'] ?? [])->filter(fn ($item) => is_array($item))->values();
        $teacherStudents = collect($insight['students'] ?? [])->filter(fn ($item) => is_array($item))->values();
        $teacherTimetable = collect($insight['timetable'] ?? [])->filter(fn ($item) => is_array($item))->values();

        $studentParents = collect($insight['parents'] ?? [])->filter(fn ($item) => is_array($item))->values();
        $studentSubjects = collect($insight['subjects'] ?? [])->filter(fn ($item) => is_array($item))->values();
        $studentScores = collect($insight['recent_scores'] ?? [])->filter(fn ($item) => is_array($item))->values();
        $studentClassData = is_array($insight['class_data'] ?? null) ? $insight['class_data'] : [];

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
                ['label' => 'Linked Profiles', 'value' => count($children)],
            ];
        }
    @endphp

    <style>
        .profile-shell {
            display: grid;
            gap: 18px;
        }

        .profile-hero {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            border: 1px solid rgba(20, 66, 42, 0.16);
            background: linear-gradient(145deg, #f7faf7 0%, #eef5ef 48%, #f6faf3 100%);
            box-shadow: 0 18px 36px rgba(12, 58, 39, 0.12);
        }

        .profile-cover {
            height: 120px;
            background:
                radial-gradient(420px 160px at 12% 12%, rgba(16, 124, 96, 0.24), transparent 72%),
                radial-gradient(420px 160px at 88% -8%, rgba(88, 149, 255, 0.22), transparent 68%),
                linear-gradient(120deg, rgba(18, 92, 71, 0.24), rgba(155, 212, 183, 0.14));
        }

        .profile-head {
            position: relative;
            margin-top: -48px;
            padding: 0 22px 20px;
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .profile-avatar {
            width: 96px;
            height: 96px;
            border-radius: 24px;
            border: 4px solid #fff;
            background: linear-gradient(150deg, #145c47 0%, #2a8f6f 100%);
            color: #fff;
            font-size: 34px;
            font-weight: 800;
            display: grid;
            place-items: center;
            box-shadow: 0 12px 24px rgba(10, 56, 40, 0.2);
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-main {
            flex: 1;
            min-width: 260px;
        }

        .profile-main h2 {
            margin: 0;
            font-size: clamp(1.45rem, 2vw, 2rem);
            line-height: 1.15;
            color: #132e24;
        }

        .profile-line {
            margin: 6px 0 0;
            color: #365949;
            font-weight: 600;
        }

        .profile-tags {
            margin-top: 10px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .profile-tag {
            border-radius: 999px;
            padding: 6px 12px;
            border: 1px solid rgba(20, 66, 42, 0.18);
            background: rgba(255, 255, 255, 0.78);
            color: #214e3d;
            font-weight: 700;
            font-size: 0.84rem;
        }

        .profile-stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
            padding: 0 22px 22px;
        }

        .profile-stat {
            border-radius: 14px;
            padding: 12px;
            border: 1px solid rgba(20, 66, 42, 0.14);
            background: rgba(255, 255, 255, 0.84);
        }

        .profile-stat .label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #497261;
            margin-bottom: 6px;
        }

        .profile-stat .value {
            font-size: 1.45rem;
            font-weight: 800;
            color: #103126;
            line-height: 1;
        }

        .profile-grid {
            display: grid;
            gap: 16px;
        }

        .profile-grid-two {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 14px;
        }

        .info-card {
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            padding: 12px;
            background: rgba(255, 255, 255, 0.72);
        }

        .info-card .label {
            font-size: 0.8rem;
            color: #5f6f67;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .info-card .value {
            font-size: 1.02rem;
            font-weight: 700;
            color: #1a2e25;
        }

        .chip-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .chip {
            border-radius: 999px;
            border: 1px solid rgba(16, 87, 66, 0.18);
            background: rgba(255, 255, 255, 0.82);
            padding: 6px 11px;
            color: #1d5946;
            font-size: 0.86rem;
            font-weight: 700;
        }

        .profile-table {
            width: 100%;
            overflow-x: auto;
        }

        .profile-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .profile-table th,
        .profile-table td {
            border-bottom: 1px solid var(--line);
            padding: 10px 8px;
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        .profile-table th {
            font-size: 0.82rem;
            text-transform: uppercase;
            color: #5f6f67;
            letter-spacing: 0.05em;
        }

        .profile-empty {
            color: #6a7e73;
            padding: 10px 0;
        }
    </style>

    <h1 class="title">My Profile</h1>
    <p class="subtitle">
        {{ $role === 'teacher' ? 'My class, timetable, and students in one view.' : '' }}
        {{ $role === 'student' ? 'My information, class, grade, and recent results.' : '' }}
        {{ $role === 'parent' ? 'My profile and children information in one easy view.' : '' }}
        @if(!in_array($role, ['teacher', 'student', 'parent'], true))
            Manage your personal information and profile photo.
        @endif
    </p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        <a href="{{ route('profile.show') }}" class="active">My Profile</a>
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <div class="profile-shell">
        <section class="profile-hero">
            <div class="profile-cover"></div>
            <div class="profile-head">
                <div class="profile-avatar">
                    @if($currentImage)
                        <img src="{{ $resolveImage($currentImage) }}" alt="Profile image">
                    @else
                        <span>{{ $avatarText !== '' ? $avatarText : 'U' }}</span>
                    @endif
                </div>
                <div class="profile-main">
                    <h2>{{ $user['name'] ?? '-' }}</h2>
                    <p class="profile-line">{{ $user['email'] ?? '-' }}</p>
                    <div class="profile-tags">
                        <span class="profile-tag">{{ $roleLabels[$role] ?? ucfirst($role !== '' ? $role : 'user') }}</span>
                        <span class="profile-tag">Phone: {{ $user['phone'] ?? '-' }}</span>
                        <span class="profile-tag">Address: {{ $user['address'] ?? '-' }}</span>
                    </div>
                </div>
            </div>
            @if($roleStats !== [])
                <div class="profile-stat-grid">
                    @foreach($roleStats as $stat)
                        <div class="profile-stat">
                            <div class="label">{{ $stat['label'] }}</div>
                            <div class="value">{{ $stat['value'] }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        @if($role === 'teacher')
            <section class="panel panel-form panel-spaced">
                <div class="panel-head">My Classes</div>
                @if($teacherClasses->isEmpty())
                    <p class="profile-empty">No class assignment found for this teacher.</p>
                @else
                    <div class="profile-grid-two">
                        @foreach($teacherClasses as $class)
                            <article class="info-card">
                                <div class="label">{{ $class['name'] ?? '-' }}</div>
                                <div class="value">
                                    Grade {{ $class['grade_level'] !== '' ? $class['grade_level'] : '-' }}
                                    / Room {{ $class['room'] !== '' ? $class['room'] : '-' }}
                                </div>
                                <div class="chip-list" style="margin-top:10px;">
                                    <span class="chip">Students: {{ $class['students_count'] ?? 0 }}</span>
                                    <span class="chip">Subjects: {{ $class['subjects_count'] ?? 0 }}</span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="panel panel-form panel-spaced">
                <div class="panel-head">My Timetable</div>
                @if($teacherTimetable->isEmpty())
                    <p class="profile-empty">No timetable rows found.</p>
                @else
                    <div class="profile-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($teacherTimetable as $row)
                                    <tr>
                                        <td>{{ $row['day'] ?? '-' }}</td>
                                        <td>{{ $row['time_start'] ?? '-' }} - {{ $row['time_end'] ?? '-' }}</td>
                                        <td>{{ $row['class_name'] ?? '-' }}</td>
                                        <td>{{ $row['subject_name'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="panel panel-form panel-spaced">
                <div class="panel-head">My Students</div>
                @if($teacherStudents->isEmpty())
                    <p class="profile-empty">No student found in your assigned classes.</p>
                @else
                    <div class="profile-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Class</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($teacherStudents as $student)
                                    <tr>
                                        <td>{{ $student['name'] ?? '-' }}</td>
                                        <td>{{ $student['class_name'] ?? '-' }}</td>
                                        <td>{{ $student['grade'] !== '' ? $student['grade'] : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif

        @if($role === 'student')
            <section class="panel panel-form panel-spaced">
                <div class="panel-head">My Information</div>
                <div class="profile-grid-two">
                    <article class="info-card">
                        <div class="label">Class</div>
                        <div class="value">{{ $studentProfile['class']['name'] ?? ($studentClassData['name'] ?? '-') }}</div>
                    </article>
                    <article class="info-card">
                        <div class="label">Grade</div>
                        <div class="value">{{ $studentProfile['grade'] ?? '-' }}</div>
                    </article>
                    <article class="info-card">
                        <div class="label">Student Code</div>
                        <div class="value">{{ $studentProfile['student_code'] ?? '-' }}</div>
                    </article>
                    <article class="info-card">
                        <div class="label">Parent Name</div>
                        <div class="value">{{ $studentProfile['parent_name'] ?? '-' }}</div>
                    </article>
                </div>
            </section>

            <section class="panel panel-form panel-spaced">
                <div class="panel-head">Parents / Guardians</div>
                @if($studentParents->isEmpty())
                    <p class="profile-empty">No parent account linked yet.</p>
                @else
                    <div class="profile-grid-two">
                        @foreach($studentParents as $parent)
                            <article class="info-card">
                                <div class="label">Name</div>
                                <div class="value">{{ $parent['name'] ?? '-' }}</div>
                                <div class="label" style="margin-top:8px;">Phone</div>
                                <div class="value">{{ $parent['phone'] !== '' ? $parent['phone'] : '-' }}</div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="panel panel-form panel-spaced">
                <div class="panel-head">My Subjects</div>
                @if($studentSubjects->isEmpty())
                    <p class="profile-empty">No subject list found for your class yet.</p>
                @else
                    <div class="profile-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Full Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($studentSubjects as $subject)
                                    <tr>
                                        <td>{{ $subject['name'] ?? '-' }}</td>
                                        <td>{{ number_format((float) ($subject['full_score'] ?? 100), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="panel panel-form panel-spaced">
                <div class="panel-head">Recent Scores</div>
                @if($studentScores->isEmpty())
                    <p class="profile-empty">No score records yet.</p>
                @else
                    <div class="profile-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Type</th>
                                    <th>Total</th>
                                    <th>Grade</th>
                                    <th>Rank</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($studentScores as $score)
                                    <tr>
                                        <td>{{ $score['subject_name'] ?? '-' }}</td>
                                        <td>{{ ucfirst((string) ($score['assessment_type'] ?? '-')) }}</td>
                                        <td>{{ number_format((float) ($score['total_score'] ?? 0), 2) }}</td>
                                        <td>{{ $score['grade'] ?? '-' }}</td>
                                        <td>{{ $score['rank_in_class'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif

        @if($role === 'parent')
            <section class="panel panel-form panel-spaced">
                <div class="panel-head">My Children</div>
                @if($parentChildren->isEmpty())
                    <p class="profile-empty">No child linked to this parent account yet.</p>
                @else
                    <div class="profile-grid-two">
                        @foreach($parentChildren as $child)
                            <article class="info-card">
                                <div class="label">{{ $child['name'] ?? '-' }}</div>
                                <div class="value">
                                    Class: {{ $child['class_name'] ?? '-' }}
                                    / Grade: {{ ($child['grade'] ?? '') !== '' ? $child['grade'] : '-' }}
                                </div>
                                <div class="chip-list" style="margin-top:10px;">
                                    <span class="chip">Avg: {{ isset($child['score_average']) ? number_format((float) $child['score_average'], 2) : '-' }}</span>
                                    <span class="chip">Scores: {{ $child['score_count'] ?? 0 }}</span>
                                    <span class="chip">Rank: {{ $child['latest_rank'] ?? '-' }}</span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        <section class="panel panel-form panel-spaced">
            <div class="panel-head">Profile Details</div>
            <div class="form-grid-wide">
                <div>
                    <label>Name</label>
                    <input type="text" value="{{ $user['name'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Email</label>
                    <input type="text" value="{{ $user['email'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Role</label>
                    <input type="text" value="{{ $roleLabels[$role] ?? ($user['role'] ?? '-') }}" disabled>
                </div>
                <div>
                    <label>Phone</label>
                    <input type="text" value="{{ $user['phone'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Address</label>
                    <input type="text" value="{{ $user['address'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>School ID</label>
                    <input type="text" value="{{ $user['school_id'] ?? '-' }}" disabled>
                </div>
            </div>
        </section>

        <form method="POST" action="{{ route('profile.update') }}" class="panel panel-form" enctype="multipart/form-data">
            @csrf
            @method('PATCH')
            <div class="panel-head">Update Profile</div>

            <label>Name</label>
            <input type="text" name="name" value="{{ old('name', $user['name'] ?? '') }}">

            <label>Phone</label>
            <input type="text" name="phone" value="{{ old('phone', $user['phone'] ?? '') }}">

            <label>Address</label>
            <input type="text" name="address" value="{{ old('address', $user['address'] ?? '') }}">

            <label>Bio</label>
            <textarea name="bio" rows="3">{{ old('bio', $user['bio'] ?? '') }}</textarea>

            <label>Profile Image Upload</label>
            <input type="file" name="image" accept="{{ \App\Support\ProfileImageStorage::acceptAttribute() }}">
            <p class="text-muted">{{ __('ui.common.supported_image_hint', ['max_mb' => \App\Support\ProfileImageStorage::maxUploadMb()]) }}</p>
            @if($currentImage)
                <img src="{{ $resolveImage($currentImage) }}" alt="Current profile image" class="avatar-preview">
                <label class="inline-check">
                    <input type="checkbox" name="remove_image" value="1" {{ old('remove_image') ? 'checked' : '' }}>
                    Remove current image
                </label>
            @endif

            <label>Image URL (optional)</label>
            <input type="text" name="image_url" value="{{ $currentImage }}" placeholder="/storage/profiles/example.jpg or https://...">

            <button type="submit" class="btn-space-top">Save Profile</button>
        </form>

        <form method="POST" action="{{ route('profile.change-password') }}" class="panel panel-form">
            @csrf
            <div class="panel-head">Change Password</div>

            <p class="text-muted">Set a new password for your account. Current password is not required.</p>

            <label>New Password</label>
            <input type="password" name="new_password" autocomplete="new-password" required placeholder="Enter your new password">

            <label>Confirm New Password</label>
            <input type="password" name="new_password_confirmation" autocomplete="new-password" required placeholder="Re-enter your new password">

            <p class="text-muted">Use at least 8 characters with uppercase, lowercase, number, and symbol. Other sessions will be logged out after update.</p>
            <button type="submit" class="btn-space-top">Update Password</button>
        </form>
    </div>
@endsection
