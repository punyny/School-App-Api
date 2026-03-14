@extends('web.layouts.app')

@section('content')
    <h1 class="title">Notification Management (API)</h1>
    <p class="subtitle">Manage user notifications and read status.</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        @can('web-manage-notifications')
        <a href="{{ route('panel.notifications.create') }}" class="active">+ Create Notification</a>
        @endcan
        
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('panel.notifications.broadcast') }}" class="panel panel-form panel-spaced" id="broadcast-form">
        @csrf
        <div class="panel-head">Quick Broadcast (Teacher / Student / Class)</div>
        <p class="subtitle">ផ្ញើ notification ទៅ Teacher ម្នាក់, All Teachers, Student ម្នាក់, All Students, ឬ Class មួយ។</p>
        <div class="form-grid">
            <select name="audience" id="broadcast-audience" required>
                <option value="">Select audience</option>
                <option value="teacher" {{ old('audience') === 'teacher' ? 'selected' : '' }}>Teacher (one)</option>
                <option value="all_teacher" {{ old('audience') === 'all_teacher' ? 'selected' : '' }}>All Teachers</option>
                <option value="student" {{ old('audience') === 'student' ? 'selected' : '' }}>Student (one)</option>
                <option value="all_student" {{ old('audience') === 'all_student' ? 'selected' : '' }}>All Students</option>
                <option value="class" {{ old('audience') === 'class' ? 'selected' : '' }}>One Class</option>
            </select>

            <div id="broadcast-user-wrap">
                <select name="user_id_select" id="broadcast-user">
                    <option value="">Select user</option>
                    @foreach(($broadcastUserOptions ?? []) as $option)
                        <option value="{{ $option['id'] }}" data-role="{{ $option['role'] }}" {{ (string) old('user_id') === (string) $option['id'] ? 'selected' : '' }}>{{ $option['label'] }} - ID: {{ $option['id'] }}</option>
                    @endforeach
                </select>
                <p class="text-muted subtitle-tight">If list is empty for your role, input user id below.</p>
                <input type="number" name="user_id_manual" id="broadcast-user-manual" placeholder="Or input User ID manually" value="{{ old('user_id_manual') }}">
            </div>

            <div id="broadcast-class-wrap">
                <select name="class_id" id="broadcast-class">
                    <option value="">Select class</option>
                    @foreach(($classOptions ?? []) as $option)
                        <option value="{{ $option['id'] }}" {{ (string) old('class_id') === (string) $option['id'] ? 'selected' : '' }}>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <input type="text" name="title" value="{{ old('title') }}" placeholder="Title" required>
            <textarea name="content" rows="3" placeholder="Message content" required>{{ old('content') }}</textarea>
            <input type="datetime-local" name="send_at" value="{{ old('send_at') }}">
        </div>
        <button type="submit" class="btn-space-top">Send Broadcast</button>
    </form>

    <form method="GET" action="{{ route('panel.notifications.index') }}" class="panel panel-form panel-spaced">
        <div class="form-grid">
            <input type="number" name="user_id" placeholder="User ID" value="{{ $filters['user_id'] ?? '' }}">
            <select name="read_status">
                <option value="">Read Status</option>
                <option value="1" {{ ($filters['read_status'] ?? '') === '1' ? 'selected' : '' }}>Read</option>
                <option value="0" {{ ($filters['read_status'] ?? '') === '0' ? 'selected' : '' }}>Unread</option>
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            <input type="number" name="per_page" placeholder="Per Page" value="{{ $filters['per_page'] ?? 20 }}">
        </div>
        <button type="submit" class="btn-space-top">Filter</button>
    </form>

    <section class="panel">
        <div class="panel-head">Notifications</div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Title</th>
                <th>Content</th>
                <th>Date</th>
                <th>Read</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item['id'] }}</td>
                    <td>{{ $item['user']['name'] ?? $item['user_id'] }}</td>
                    <td>{{ $item['title'] }}</td>
                    <td>{{ $item['content'] }}</td>
                    <td>{{ $item['date'] }}</td>
                    <td>{{ !empty($item['read_status']) ? 'Yes' : 'No' }}</td>
                    <td>
                        @can('web-manage-notifications')
                        <a href="{{ route('panel.notifications.edit', $item['id']) }}">Edit</a>
                        
                        <form action="{{ route('panel.notifications.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Delete this notification?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Delete</button>
                        </form>
                        @endcan
                        
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">No data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <script>
        (function () {
            const audience = document.getElementById('broadcast-audience');
            const userWrap = document.getElementById('broadcast-user-wrap');
            const classWrap = document.getElementById('broadcast-class-wrap');
            const userSelect = document.getElementById('broadcast-user');
            const userManual = document.getElementById('broadcast-user-manual');

            if (!audience || !userWrap || !classWrap || !userSelect || !userManual) {
                return;
            }

            const syncVisibility = () => {
                const value = audience.value;
                userWrap.style.display = (value === 'teacher' || value === 'student') ? 'block' : 'none';
                classWrap.style.display = (value === 'class') ? 'block' : 'none';
            };

            syncVisibility();
            audience.addEventListener('change', syncVisibility);

            userManual.addEventListener('input', () => {
                if (userManual.value.trim() !== '') {
                    userSelect.value = '';
                }
            });

            userSelect.addEventListener('change', () => {
                if (userSelect.value) {
                    userManual.value = '';
                }
            });
        })();
    </script>
@endsection
