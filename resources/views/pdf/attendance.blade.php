<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { margin: 0 0 6px 0; font-size: 18px; }
        .meta { margin-bottom: 12px; color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Attendance Report</h1>
    <div class="meta">Generated at: {{ $generatedAt }}</div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Subject</th>
                <th>Time Start</th>
                <th>Time End</th>
                <th>Status</th>
                <th>Student</th>
                <th>Class</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->date }}</td>
                    <td>{{ $row->subject?->name ?? 'General Attendance' }}</td>
                    <td>{{ $row->time_start }}</td>
                    <td>{{ $row->time_end }}</td>
                    <td>{{ $row->status }}</td>
                    <td>{{ $row->student?->user?->name }}</td>
                    <td>{{ $row->class?->name }}</td>
                    <td>{{ $row->remarks }}</td>
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
