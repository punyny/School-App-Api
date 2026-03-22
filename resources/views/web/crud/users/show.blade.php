@extends('web.layouts.app')

@section('content')
    @php
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
    @endphp

    <h1 class="title">User Detail</h1>
    <p class="subtitle">View complete user information and related details.</p>

    <div class="nav">
        <a href="{{ route('panel.users.index') }}">Back to list</a>
        @if(!empty($item['id']))
            <a href="{{ route('panel.users.edit', $item['id']) }}" class="active">Edit User</a>
        @endif
    </div>

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    @if (empty($item))
        <section class="panel">
            <div class="empty">User data not found.</div>
        </section>
    @else
        @php
            $studentProfile = is_array($item['student_profile'] ?? null) ? $item['student_profile'] : null;
            $children = collect($item['children'] ?? [])->filter(fn ($child) => is_array($child))->values();
            $teachingClasses = collect($item['teaching_classes'] ?? [])->filter(fn ($class) => is_array($class))->values();
        @endphp

        <section class="panel panel-form panel-spaced">
            <div class="panel-head">User Information</div>
            @if(!empty($item['image_url']))
                <img src="{{ $resolveImage($item['image_url']) }}" alt="User image" class="avatar-preview">
            @endif

            <div class="form-grid-wide">
                <div>
                    <label>User ID</label>
                    <input type="text" value="{{ $item['id'] }}" disabled>
                </div>
                <div>
                    <label>Name</label>
                    <input type="text" value="{{ $item['name'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>User Code</label>
                    <input type="text" value="{{ $item['user_code'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Khmer Name</label>
                    <input type="text" value="{{ $item['khmer_name'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>First Name</label>
                    <input type="text" value="{{ $item['first_name'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Last Name</label>
                    <input type="text" value="{{ $item['last_name'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Email</label>
                    <input type="text" value="{{ $item['email'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Email Verified</label>
                    <input type="text" value="{{ !empty($item['email_verified_at']) ? 'Verified' : 'Pending verification' }}" disabled>
                </div>
                <div>
                    <label>Role</label>
                    <input type="text" value="{{ $item['role'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>School</label>
                    <input type="text" value="{{ $item['school']['name'] ?? ($item['school_id'] ?? '-') }}" disabled>
                </div>
                <div>
                    <label>Phone</label>
                    <input type="text" value="{{ $item['phone'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Gender</label>
                    <input type="text" value="{{ $item['gender'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Date of Birth</label>
                    <input type="text" value="{{ !empty($item['dob']) ? \Illuminate\Support\Carbon::parse($item['dob'])->toDateString() : '-' }}" disabled>
                </div>
                <div>
                    <label>Address</label>
                    <input type="text" value="{{ $item['address'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Status</label>
                    <input type="text" value="{{ !empty($item['active']) ? 'Active' : 'Inactive' }}" disabled>
                </div>
            </div>

            <label>Bio</label>
            <textarea rows="3" disabled>{{ $item['bio'] ?? '-' }}</textarea>

            @if (empty($item['email_verified_at']) && !empty($item['id']))
                <form method="POST" action="{{ route('panel.users.resend-verification', $item['id']) }}" style="margin-top:16px;">
                    @csrf
                    <button type="submit">Resend verification email</button>
                </form>
            @endif
        </section>

        @if($studentProfile)
            <section class="panel panel-form">
                <div class="panel-head">Student Profile</div>
                <div class="form-grid-wide">
                    <div>
                        <label>Student ID</label>
                        <input type="text" value="{{ $studentProfile['id'] ?? '-' }}" disabled>
                    </div>
                    <div>
                        <label>Class</label>
                        <input type="text" value="{{ $studentProfile['class']['name'] ?? ($studentProfile['class_id'] ?? '-') }}" disabled>
                    </div>
                    <div>
                        <label>Student ID</label>
                        <input type="text" value="{{ $studentProfile['student_code'] ?? '-' }}" disabled>
                    </div>
                    <div>
                        <label>Grade</label>
                        <input type="text" value="{{ $studentProfile['grade'] ?? '-' }}" disabled>
                    </div>
                    <div>
                        <label>Parent Name</label>
                        <input type="text" value="{{ $studentProfile['parent_name'] ?? '-' }}" disabled>
                    </div>
                    <div>
                        <label>Parents Linked</label>
                        <input type="text" value="{{ count($studentProfile['parents'] ?? []) }}" disabled>
                    </div>
                </div>
            </section>
        @endif

        @if(($item['role'] ?? '') === 'parent')
            <section class="panel panel-form">
                <div class="panel-head">Linked Children</div>
                @if($children->isEmpty())
                    <div class="empty">No child linked yet.</div>
                @else
                    <div class="form-grid-wide">
                        @foreach($children as $child)
                            <div>
                                <label>Child</label>
                                <input type="text" value="{{ ($child['user']['name'] ?? 'Student').' | '.($child['class']['name'] ?? 'No class').' | ID '.($child['student_code'] ?? $child['id'] ?? '-') }}" disabled>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        @if(($item['role'] ?? '') === 'teacher')
            <section class="panel panel-form">
                <div class="panel-head">Teaching Assignment Summary</div>
                <div class="form-grid-wide">
                    <div>
                        <label>Assigned Classes</label>
                        <input type="text" value="{{ $teachingClasses->count() }}" disabled>
                    </div>
                </div>
            </section>
        @endif
    @endif
@endsection
