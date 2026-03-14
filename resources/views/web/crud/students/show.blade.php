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

    <h1 class="title">Student Detail</h1>
    <p class="subtitle">View full student profile and linked records.</p>

    <div class="nav">
        <a href="{{ route('panel.students.index') }}">Back to list</a>
        @if(!empty($item['id']))
            <a href="{{ route('panel.students.edit', $item['id']) }}" class="active">Edit Student</a>
        @endif
    </div>

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    @if (empty($item))
        <section class="panel">
            <div class="empty">Student data not found.</div>
        </section>
    @else
        @php
            $user = is_array($item['user'] ?? null) ? $item['user'] : [];
            $class = is_array($item['class'] ?? null) ? $item['class'] : [];
            $parents = is_array($item['parents'] ?? null) ? $item['parents'] : [];
        @endphp

        <section class="panel panel-form panel-spaced">
            <div class="panel-head">Student Information</div>
            @if(!empty($user['image_url']))
                <img src="{{ $resolveImage($user['image_url']) }}" alt="Student image" class="avatar-preview">
            @endif

            <div class="form-grid-wide">
                <div>
                    <label>Student ID</label>
                    <input type="text" value="{{ $item['id'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>User ID</label>
                    <input type="text" value="{{ $item['user_id'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Name</label>
                    <input type="text" value="{{ $user['name'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Student Code</label>
                    <input type="text" value="{{ $item['student_code'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Khmer Name</label>
                    <input type="text" value="{{ $user['khmer_name'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>First Name</label>
                    <input type="text" value="{{ $user['first_name'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Last Name</label>
                    <input type="text" value="{{ $user['last_name'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Email</label>
                    <input type="text" value="{{ $user['email'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Phone</label>
                    <input type="text" value="{{ $user['phone'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Class</label>
                    <input type="text" value="{{ $class['name'] ?? ($item['class_id'] ?? '-') }}" disabled>
                </div>
                <div>
                    <label>Grade</label>
                    <input type="text" value="{{ $item['grade'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Parent Name</label>
                    <input type="text" value="{{ $item['parent_name'] ?? '-' }}" disabled>
                </div>
                <div>
                    <label>Parents Linked</label>
                    <input type="text" value="{{ count($parents) }}" disabled>
                </div>
            </div>

            <label>Address</label>
            <input type="text" value="{{ $user['address'] ?? '-' }}" disabled>

            <label>Bio</label>
            <textarea rows="3" disabled>{{ $user['bio'] ?? '-' }}</textarea>
        </section>

        <section class="panel panel-form">
            <div class="panel-head">Parent Information</div>
            @if(count($parents) === 0)
                <div class="empty">No parent linked.</div>
            @else
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($parents as $parent)
                        <tr>
                            <td>{{ $parent['id'] ?? '-' }}</td>
                            <td>{{ $parent['name'] ?? '-' }}</td>
                            <td>{{ $parent['email'] ?? '-' }}</td>
                            <td>{{ $parent['phone'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endif
@endsection
