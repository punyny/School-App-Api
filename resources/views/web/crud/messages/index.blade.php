@extends('web.layouts.app')

@section('content')
    <h1 class="title">Message Management</h1>
    <p class="subtitle">Manage personal, class, and group messages.</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        @if($canCreateMessage ?? false)
        <a href="{{ route('panel.messages.create') }}" class="active">+ Create Message</a>
        @endif
        
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    @php
        $selectedClassId = (string) ($filters['class_id'] ?? '');
        $classSelectOptions = collect($classOptions ?? []);

        if ($selectedClassId !== '' && ! $classSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedClassId)) {
            $classSelectOptions = $classSelectOptions->prepend([
                'id' => (int) $selectedClassId,
                'label' => 'Class ID: '.$selectedClassId,
            ]);
        }
    @endphp

    <form method="GET" action="{{ route('panel.messages.index') }}" class="panel panel-form panel-spaced">
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
            <input type="number" name="sender_id" placeholder="Sender ID" value="{{ $filters['sender_id'] ?? '' }}">
            <input type="number" name="receiver_id" placeholder="Receiver ID" value="{{ $filters['receiver_id'] ?? '' }}">
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            <input type="number" name="per_page" placeholder="Per Page" value="{{ $filters['per_page'] ?? 20 }}">
        </div>
        <button type="submit" class="btn-space-top">Filter</button>
    </form>

    <section class="panel">
        <div class="panel-head">Messages</div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Sender</th>
                <th>Receiver</th>
                <th>Class</th>
                <th>Content</th>
                <th>Seen</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item['id'] }}</td>
                    <td>{{ $item['date'] }}</td>
                    <td>{{ $item['sender']['name'] ?? $item['sender_id'] }}</td>
                    <td>{{ $item['receiver']['name'] ?? ($item['receiver_id'] ?? '-') }}</td>
                    <td>{{ $item['class']['name'] ?? ($item['class_id'] ?? '-') }}</td>
                    <td>{{ $item['content'] }}</td>
                    <td>
                        @php
                            $readMeta = $item['read_meta'] ?? [];
                            $isDirect = !empty($item['receiver_id']);
                            $directSeenAt = $readMeta['direct_recipient_seen_at'] ?? null;
                            $seenCount = (int) ($readMeta['seen_count'] ?? 0);
                            $recipientCount = (int) ($readMeta['recipient_count'] ?? 0);
                        @endphp
                        @if($isDirect)
                            {{ $directSeenAt ? 'Seen at '.$directSeenAt : 'Not seen yet' }}
                        @else
                            {{ $seenCount }}/{{ $recipientCount }} seen
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('panel.messages.show', $item['id']) }}">View</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">No data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
