@extends('web.layouts.app')

@section('content')
    <h1 class="title">Audit Log (API)</h1>
    <p class="subtitle">Track who did what and when (Admin / Super-admin).</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="GET" action="{{ route('panel.audit-logs.index') }}" class="panel panel-form panel-spaced">
        <div class="form-grid">
            <input type="number" name="actor_id" placeholder="Actor ID" value="{{ $filters['actor_id'] ?? '' }}">
            <select name="actor_role">
                <option value="">Actor Role</option>
                @foreach (['super-admin', 'admin', 'teacher', 'student', 'parent'] as $role)
                    <option value="{{ $role }}" {{ ($filters['actor_role'] ?? '') === $role ? 'selected' : '' }}>
                        {{ $role }}
                    </option>
                @endforeach
            </select>
            <select name="method">
                <option value="">Method</option>
                @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method)
                    <option value="{{ $method }}" {{ ($filters['method'] ?? '') === $method ? 'selected' : '' }}>
                        {{ $method }}
                    </option>
                @endforeach
            </select>
            <input type="text" name="resource_type" placeholder="Resource Type" value="{{ $filters['resource_type'] ?? '' }}">
            <input type="text" name="search" placeholder="Search actor/action/endpoint" value="{{ $filters['search'] ?? '' }}">
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            <input type="number" name="per_page" placeholder="Per Page" value="{{ $filters['per_page'] ?? 20 }}">
        </div>
        <button type="submit" class="btn-space-top">Filter</button>
    </form>

    <section class="panel">
        <div class="panel-head">Audit Entries</div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>When</th>
                <th>Who</th>
                <th>Role</th>
                <th>School</th>
                <th>Action</th>
                <th>Status</th>
                <th>IP</th>
                <th>Payload</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                @php
                    $payloadText = '';
                    if (is_array($item['request_payload'] ?? null)) {
                        $payloadText = json_encode($item['request_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                    }
                @endphp
                <tr>
                    <td>{{ $item['id'] }}</td>
                    <td>{{ $item['created_at'] ?? '-' }}</td>
                    <td>{{ $item['actor']['name'] ?? ($item['actor_name'] ?? '-') }}</td>
                    <td>{{ $item['actor_role'] ?? '-' }}</td>
                    <td>{{ $item['school']['name'] ?? ($item['school_id'] ?? '-') }}</td>
                    <td>
                        {{ $item['action'] ?? '-' }}<br>
                        <span class="text-muted">{{ $item['endpoint'] ?? '-' }}</span>
                    </td>
                    <td>{{ $item['status_code'] ?? '-' }}</td>
                    <td>{{ $item['ip_address'] ?? '-' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($payloadText, 120) }}</td>
                </tr>
            @empty
                <tr><td colspan="9">No data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection

