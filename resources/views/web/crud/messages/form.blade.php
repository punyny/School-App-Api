@extends('web.layouts.app')

@section('content')
    <h1 class="title">Create Message</h1>
    <p class="subtitle">Telegram-style messaging: personal (one-to-one) or class broadcast (one-to-many).</p>

    <div class="nav">
        <a href="{{ route('panel.messages.index') }}">Back to list</a>
    </div>

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    @php
        $selectedReceiverId = (string) old('receiver_id', $item['receiver_id'] ?? '');
        $receiverSelectOptions = collect($receiverOptions ?? []);
        if ($selectedReceiverId !== '' && ! $receiverSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedReceiverId)) {
            $receiverSelectOptions = $receiverSelectOptions->prepend([
                'id' => (int) $selectedReceiverId,
                'label' => 'User ID: '.$selectedReceiverId,
            ]);
        }
    @endphp

    <form method="POST" action="{{ route('panel.messages.store') }}" class="panel panel-form">
        @csrf

        <label>Direct Receiver (Personal Message)</label>
        <div class="searchable-select-wrap">
            <input type="text" class="searchable-select-search" placeholder="Search receiver..." data-select-search-for="receiver_id">
            <select id="receiver_id" name="receiver_id">
                <option value="">No direct receiver</option>
                @foreach($receiverSelectOptions as $option)
                    <option value="{{ $option['id'] }}" {{ $selectedReceiverId === (string) $option['id'] ? 'selected' : '' }}>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        @php
            $selectedClassId = (string) old('class_id', $item['class_id'] ?? '');
            $classSelectOptions = collect($classOptions ?? []);

            if ($selectedClassId !== '' && ! $classSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedClassId)) {
                $classSelectOptions = $classSelectOptions->prepend([
                    'id' => (int) $selectedClassId,
                    'label' => 'Class ID: '.$selectedClassId,
                ]);
            }
        @endphp

        <label>Class Broadcast (Group Message)</label>
        <div class="searchable-select-wrap">
            <input type="text" class="searchable-select-search" placeholder="Search class..." data-select-search-for="class_id">
            <select id="class_id" name="class_id" {{ !($canClassBroadcast ?? false) ? 'disabled' : '' }}>
                <option value="">No class</option>
                @foreach($classSelectOptions as $option)
                    <option value="{{ $option['id'] }}" {{ $selectedClassId === (string) $option['id'] ? 'selected' : '' }}>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
        </div>
        @if(!($canClassBroadcast ?? false))
            <p class="text-muted">Your role can only send direct message (receiver), not class broadcast.</p>
        @endif

        <label>Content</label>
        <textarea name="content" rows="5" required>{{ old('content', $item['content'] ?? '') }}</textarea>

        <label>Date/Time (optional)</label>
        <input type="datetime-local" name="date" value="{{ old('date', isset($item['date']) ? str_replace(' ', 'T', substr((string) $item['date'], 0, 16)) : '') }}">

        <button type="submit" class="btn-space-top">Create</button>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var receiverSelect = document.getElementById('receiver_id');
            var classSelect = document.getElementById('class_id');

            if (!receiverSelect || !classSelect) {
                return;
            }

            receiverSelect.addEventListener('change', function () {
                if ((receiverSelect.value || '') !== '') {
                    classSelect.value = '';
                }
            });

            classSelect.addEventListener('change', function () {
                if ((classSelect.value || '') !== '') {
                    receiverSelect.value = '';
                }
            });
        });
    </script>
@endpush
