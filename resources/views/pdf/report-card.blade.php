<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Report Card</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        .title { font-size: 20px; font-weight: bold; margin-bottom: 8px; }
        .meta { margin-bottom: 16px; }
        .meta div { margin: 2px 0; }
        .summary { margin-bottom: 16px; }
        .summary span { display: inline-block; min-width: 120px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    @php
        $khmerMonths = \App\Support\KhmerMonth::options();
    @endphp

    <div class="title">Student Report Card</div>
    <div class="meta">
        <div><strong>Name:</strong> {{ $student->user?->name }}</div>
        <div><strong>Student ID:</strong> {{ $student->id }}</div>
        <div><strong>Class:</strong> {{ $student->class?->name }}</div>
        <div><strong>Generated At:</strong> {{ $generatedAt->format('Y-m-d H:i') }}</div>
    </div>

    <div class="summary">
        <span><strong>GPA:</strong> {{ $summary['gpa'] }}</span>
        <span><strong>Average:</strong> {{ $summary['average_score'] }}</span>
        <span><strong>Overall Grade:</strong> {{ $summary['overall_grade'] }}</span>
        <span><strong>Rank In Class:</strong> {{ $summary['rank_in_class'] ?? '-' }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Subject</th>
                <th>Average Score</th>
                <th>Grade</th>
                <th>Entries</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subjects as $subject)
                <tr>
                    <td>{{ $subject['subject_name'] }}</td>
                    <td>{{ $subject['average_score'] }}</td>
                    <td>{{ $subject['grade'] }}</td>
                    <td>
                        @foreach ($subject['entries'] as $entry)
                            <div>
                                {{ ucfirst((string) ($entry['assessment_type'] ?? 'monthly')) }}
                                @if(!empty($entry['month']))
                                    / {{ $khmerMonths[(int) $entry['month']] ?? ('Month '.$entry['month']) }}
                                @endif
                                @if(!empty($entry['semester']))
                                    / ឆមាសទី {{ $entry['semester'] }}
                                @endif
                                @if(!empty($entry['quarter']))
                                    / Q{{ $entry['quarter'] }}
                                @endif
                                @if(!empty($entry['period']))
                                    / {{ $entry['period'] }}
                                @endif
                                : {{ $entry['total_score'] }} ({{ $entry['grade'] }})
                            </div>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No score records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
