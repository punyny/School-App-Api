<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Homeworks Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { margin: 0 0 6px 0; font-size: 18px; }
        .meta { margin-bottom: 12px; color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Homeworks Report</h1>
    <div class="meta">Generated at: {{ $generatedAt }}</div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Class</th>
                <th>Subject</th>
                <th>Title</th>
                <th>Question</th>
                <th>Due Date</th>
                <th>Due Time</th>
                <th>Done</th>
                <th>Not Done</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $doneCount = $row->statuses->where('status', 'Done')->count();
                    $notDoneCount = $row->statuses->where('status', 'Not Done')->count();
                @endphp
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->class?->name }}</td>
                    <td>{{ $row->subject?->name }}</td>
                    <td>{{ $row->title }}</td>
                    <td>{{ $row->question }}</td>
                    <td>{{ $row->due_date }}</td>
                    <td>{{ $row->due_time ? \Illuminate\Support\Str::substr((string) $row->due_time, 0, 5) : '-' }}</td>
                    <td>{{ $doneCount }}</td>
                    <td>{{ $notDoneCount }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">No data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
