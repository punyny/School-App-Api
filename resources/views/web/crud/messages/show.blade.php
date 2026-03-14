@extends('web.layouts.app')

@section('content')
    <h1 class="title">View Message</h1>
    <p class="subtitle">Read-only message details with seen status and time.</p>

    <div class="nav">
        <a href="{{ route('panel.messages.index') }}">Back to list</a>
        @if(($canCreateMessage ?? false) === true)
            <a href="{{ route('panel.messages.create') }}" class="active">+ Create Message</a>
        @endif
    </div>

    @php
        $item = $item ?? [];
        $readMeta = $item['read_meta'] ?? [];
        $seenBy = is_array($readMeta['seen_by'] ?? null) ? $readMeta['seen_by'] : [];
        $isDirect = !empty($item['receiver_id']);
    @endphp

    <section class="panel panel-form">
        <label>Date</label>
        <input type="text" value="{{ $item['date'] ?? '-' }}" disabled>

        <label>Sender</label>
        <input type="text" value="{{ $item['sender']['name'] ?? ($item['sender_id'] ?? '-') }}" disabled>

        <label>Receiver</label>
        <input type="text" value="{{ $item['receiver']['name'] ?? ($item['receiver_id'] ?? '-') }}" disabled>

        <label>Class</label>
        <input type="text" value="{{ $item['class']['name'] ?? ($item['class_id'] ?? '-') }}" disabled>

        <label>Content</label>
        <textarea rows="6" disabled>{{ $item['content'] ?? '' }}</textarea>
    </section>

    <section class="panel">
        <div class="panel-head">Seen Status</div>
        @if($isDirect)
            @php $directSeenAt = $readMeta['direct_recipient_seen_at'] ?? null; @endphp
            <p><strong>Status:</strong> {{ $directSeenAt ? 'Seen' : 'Not seen yet' }}</p>
            <p><strong>Seen at:</strong> {{ $directSeenAt ?? '-' }}</p>
        @else
            <p><strong>Seen:</strong> {{ (int) ($readMeta['seen_count'] ?? 0) }}/{{ (int) ($readMeta['recipient_count'] ?? 0) }}</p>
            <p><strong>Last seen at:</strong> {{ $readMeta['last_seen_at'] ?? '-' }}</p>
        @endif

        @if($seenBy !== [])
            <table>
                <thead>
                <tr>
                    <th>User</th>
                    <th>Seen At</th>
                </tr>
                </thead>
                <tbody>
                @foreach($seenBy as $row)
                    <tr>
                        <td>{{ $row['name'] ?? ($row['user_id'] ?? '-') }}</td>
                        <td>{{ $row['seen_at'] ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
