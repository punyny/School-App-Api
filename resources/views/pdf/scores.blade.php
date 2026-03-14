<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Scores Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { margin: 0 0 6px 0; font-size: 18px; }
        .meta { margin-bottom: 12px; color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Scores Report</h1>
    <div class="meta">Generated at: {{ $generatedAt }}</div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Student</th>
                <th>Class</th>
                <th>Subject</th>
                <th>Exam</th>
                <th>Total</th>
                <th>Type</th>
                <th>Month</th>
                <th>Semester</th>
                <th>Year</th>
                <th>Quarter</th>
                <th>Period</th>
                <th>Grade</th>
                <th>Rank</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->student?->user?->name }}</td>
                    <td>{{ $row->class?->name }}</td>
                    <td>{{ $row->subject?->name }}</td>
                    <td>{{ $row->exam_score }}</td>
                    <td>{{ $row->total_score }}</td>
                    <td>{{ ucfirst($row->assessment_type ?? 'monthly') }}</td>
                    <td>{{ $row->month }}</td>
                    <td>{{ $row->semester }}</td>
                    <td>{{ $row->academic_year }}</td>
                    <td>{{ $row->quarter }}</td>
                    <td>{{ $row->period }}</td>
                    <td>{{ $row->grade }}</td>
                    <td>{{ $row->rank_in_class }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="14">No data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
