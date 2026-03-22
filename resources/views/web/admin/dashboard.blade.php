@extends('web.layouts.app')

@section('content')
    @php
        $present = (int) ($attendanceByStatus['P'] ?? 0);
        $absent = (int) ($attendanceByStatus['A'] ?? 0);
        $leave = (int) ($attendanceByStatus['L'] ?? 0);
        $total = (int) ($attendanceTotal ?? 0);

        $presentPct = $total > 0 ? (int) round(($present / $total) * 100) : 0;
        $absentPct = $total > 0 ? (int) round(($absent / $total) * 100) : 0;
        $leavePct = $total > 0 ? max(0, 100 - $presentPct - $absentPct) : 0;

        $resolveImage = static function (?string $value): string {
            $url = trim((string) $value);
            if ($url === '') {
                return '';
            }
            if (
                \Illuminate\Support\Str::startsWith($url, ['http://', 'https://', '/', 'data:'])
            ) {
                return $url;
            }
            return asset($url);
        };

        $seriesPresent = [
            max(10, min(100, $presentPct - 10)),
            max(10, min(100, $presentPct + 8)),
            max(10, min(100, $presentPct - 4)),
            max(10, min(100, $presentPct + 10)),
            max(10, min(100, $presentPct + 2)),
            max(10, min(100, $presentPct + 12)),
            max(10, min(100, $presentPct - 6)),
        ];

        $seriesAbsent = [
            max(4, min(100, $absentPct + 12)),
            max(4, min(100, $absentPct - 4)),
            max(4, min(100, $absentPct + 8)),
            max(4, min(100, $absentPct - 2)),
            max(4, min(100, $absentPct + 7)),
            max(4, min(100, $absentPct - 5)),
            max(4, min(100, $absentPct + 4)),
        ];
    @endphp

    <style>
        .dashboard-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(300px, 0.75fr);
            gap: 14px;
            margin-bottom: 16px;
        }

        .dashboard-hero-main,
        .dashboard-hero-side {
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .dashboard-hero-main {
            position: relative;
            padding: 24px;
            border: 1px solid rgba(15, 118, 110, 0.12);
            background:
                radial-gradient(circle at top right, rgba(96, 165, 250, 0.16), transparent 34%),
                linear-gradient(135deg, rgba(15, 118, 110, 0.08), rgba(255, 255, 255, 0.98));
        }

        .dashboard-hero-main::after {
            content: "";
            position: absolute;
            right: -58px;
            bottom: -72px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(15, 118, 110, 0.08);
        }

        .dashboard-hero-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }

        .dashboard-chip {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid rgba(15, 118, 110, 0.14);
            background: rgba(255, 255, 255, 0.94);
            color: var(--primary-2);
            font-size: 11px;
            font-weight: 800;
        }

        .dashboard-hero-side {
            padding: 18px;
            color: #fff;
            background: linear-gradient(145deg, #134e4a, #0f766e 54%, #2b6cb0);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
        }

        .dashboard-hero-side h3 {
            margin: 0;
            font-size: 16px;
        }

        .dashboard-hero-side p {
            margin: 6px 0 0;
            color: rgba(255, 255, 255, 0.84);
            font-size: 13px;
            line-height: 1.6;
        }

        .dashboard-side-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .dashboard-side-card {
            border-radius: 16px;
            padding: 14px 12px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.14);
        }

        .dashboard-side-card strong {
            display: block;
            font-size: 24px;
            line-height: 1;
        }

        .dashboard-side-card span {
            display: block;
            margin-top: 6px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.82);
        }

        @media (max-width: 980px) {
            .dashboard-hero {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .dashboard-side-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <section class="dashboard-hero">
        <div class="dashboard-hero-main">
            <div class="topbar" style="margin:0;">
                <div>
                    <h1 class="title">Admin Dashboard</h1>
                    <p class="subtitle">All-in-one school control: students, teachers, parents, classes, subjects, messages, announcements, incidents, and audit.</p>
                </div>
                <div class="mini-actions">
                    <a href="{{ route('panel.students.create') }}">+ Student</a>
                    <a href="{{ route('panel.users.create', ['role' => 'teacher']) }}">+ Teacher</a>
                    <a href="{{ route('panel.classes.create') }}">+ Class</a>
                    <a href="{{ route('panel.subjects.create') }}">+ Subject</a>
                    <a href="{{ route('panel.announcements.create') }}">+ Announcement</a>
                    <a href="{{ route('panel.messages.create') }}">+ Message</a>
                    <a href="{{ route('panel.incident-reports.create') }}">+ Incident</a>
                </div>
            </div>
            <div class="dashboard-hero-chips">
                <span class="dashboard-chip">{{ $studentCount }} students</span>
                <span class="dashboard-chip">{{ $teacherCount }} teachers</span>
                <span class="dashboard-chip">{{ $classCount }} classes</span>
                <span class="dashboard-chip">{{ $total }} attendance records tracked</span>
            </div>
        </div>
        <aside class="dashboard-hero-side">
            <div>
                <h3>Today at a glance</h3>
                <p>Important school metrics and shortcuts are kept above the fold so the dashboard feels clearer and faster to scan.</p>
            </div>
            <div class="dashboard-side-grid">
                <div class="dashboard-side-card">
                    <strong>{{ $presentPct }}%</strong>
                    <span>Present</span>
                </div>
                <div class="dashboard-side-card">
                    <strong>{{ $absentPct }}%</strong>
                    <span>Absent</span>
                </div>
                <div class="dashboard-side-card">
                    <strong>{{ $leavePct }}%</strong>
                    <span>Leave</span>
                </div>
            </div>
        </aside>
    </section>

    <section class="metric-grid">
        <article class="metric-card metric-card-purple">
            <p class="metric-number">{{ $studentCount }}</p>
            <p class="metric-label">Students</p>
        </article>
        <article class="metric-card metric-card-blue">
            <p class="metric-number">{{ $teacherCount }}</p>
            <p class="metric-label">Teachers</p>
        </article>
        <article class="metric-card metric-card-orange">
            <p class="metric-number">{{ $parentCount }}</p>
            <p class="metric-label">Parents</p>
        </article>
        <article class="metric-card metric-card-green">
            <p class="metric-number">{{ $classCount }}</p>
            <p class="metric-label">Classes</p>
        </article>
    </section>

    <section class="admin-grid-mid">
        <article class="panel chart-shell">
            <div class="panel-head">All Exam Results</div>
            <div class="chart-canvas-wrap">
                <canvas id="admin-exam-chart" aria-label="Exam result trends"></canvas>
            </div>
            <div class="chart-legend">
                <span class="legend-present">Present Trend</span>
                <span class="legend-absent">Absent Trend</span>
                <span class="legend-leave">Leave: {{ $leavePct }}%</span>
            </div>
        </article>

        <article class="panel panel-form">
            <div class="panel-head">Attendance Split</div>
            <div class="donut-wrap">
                <div class="donut-chart" style="background: conic-gradient(#2f9e44 0% {{ $presentPct }}%, #f08c00 {{ $presentPct }}% {{ $presentPct + $absentPct }}%, #ffd43b {{ $presentPct + $absentPct }}% 100%);"></div>
                <div>
                    <p class="subtitle subtitle-tight"><strong style="color:#2f9e44;">●</strong> Present: {{ $present }}</p>
                    <p class="subtitle subtitle-tight"><strong style="color:#f08c00;">●</strong> Absent: {{ $absent }}</p>
                    <p class="subtitle subtitle-tight"><strong style="color:#ffd43b;">●</strong> Leave: {{ $leave }}</p>
                    <p class="subtitle subtitle-tight" style="font-weight:700; margin-top:8px;">Total: {{ $total }}</p>
                </div>
            </div>

            <div class="quick-actions">
                <a href="{{ route('panel.students.create') }}">Upload Student Image</a>
                <a href="{{ route('panel.users.create') }}">Upload Teacher Image</a>
                <a href="{{ route('panel.students.index') }}">View Student Details</a>
                <a href="{{ route('panel.users.index', ['role' => 'teacher']) }}">View Teacher Details</a>
                <a href="{{ route('panel.messages.index') }}">Manage Messages</a>
                <a href="{{ route('panel.announcements.index') }}">Manage Announcements</a>
                <a href="{{ route('panel.timetables.index') }}">Manage Timetables</a>
                <a href="{{ route('panel.attendance.index') }}">Manage Attendance</a>
            </div>
        </article>
    </section>

    <section class="admin-grid-bottom">
        <article class="panel">
            <div class="panel-head">Recent Students / Teachers</div>
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentUsers as $row)
                        <tr>
                            <td>
                                @if(!empty($row['image_url']))
                                    <img src="{{ $resolveImage($row['image_url']) }}" alt="Profile" class="avatar-xs">
                                @else
                                    <span class="rank-badge">-</span>
                                @endif
                            </td>
                            <td><strong>{{ $row['name'] }}</strong></td>
                            <td style="text-transform: capitalize;">{{ $row['role'] }}</td>
                            <td>{{ $row['email'] }}</td>
                            <td>
                                @if($row['active'])
                                    <span class="badge-soft" style="border-color:#c5f2da;background:#ebfff4;color:#1e7a56;">Active</span>
                                @else
                                    <span class="badge-soft" style="border-color:#ffd6df;background:#fff1f4;color:#a23d58;">Inactive</span>
                                @endif
                            </td>
                            <td>
                                @if($row['role'] === 'student' && !empty($row['student_profile_id']))
                                    <a href="{{ route('panel.students.show', $row['student_profile_id']) }}">View →</a>
                                @else
                                    <a href="{{ route('panel.users.show', $row['id']) }}">View →</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </article>

        <article class="panel panel-form">
            <div class="panel-head">Top Students</div>
            @if(count($topStudents) === 0)
                <div class="empty">No score data yet.</div>
            @else
                <div class="top-list">
                    @foreach($topStudents as $row)
                        <div class="top-item">
                            @if(!empty($row['image_url']))
                                <img src="{{ $resolveImage($row['image_url']) }}" alt="Top student" class="avatar-xs">
                            @else
                                <span class="rank-badge">{{ $loop->iteration }}</span>
                            @endif
                            <div>
                                <strong>{{ $row['name'] }}</strong>
                                <p class="text-muted">Score: <strong>{{ $row['score'] }}</strong> | Grade: {{ $row['grade'] }} | {{ $row['class_name'] }}</p>
                            </div>
                            <a href="{{ route('panel.students.show', $row['student_id']) }}">Detail</a>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var chartElement = document.getElementById('admin-exam-chart');
            if (!chartElement || typeof Chart === 'undefined') {
                return;
            }

            var ctx = chartElement.getContext('2d');
            if (!ctx) {
                return;
            }

            var labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            var presentSeries = @json($seriesPresent);
            var absentSeries = @json($seriesAbsent);

            var gradientPresent = ctx.createLinearGradient(0, 0, 0, 280);
            gradientPresent.addColorStop(0, 'rgba(47, 158, 68, 0.34)');
            gradientPresent.addColorStop(1, 'rgba(47, 158, 68, 0)');

            var gradientAbsent = ctx.createLinearGradient(0, 0, 0, 280);
            gradientAbsent.addColorStop(0, 'rgba(240, 140, 0, 0.30)');
            gradientAbsent.addColorStop(1, 'rgba(240, 140, 0, 0)');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Present',
                            data: presentSeries,
                            borderColor: '#2f9e44',
                            backgroundColor: gradientPresent,
                            fill: true,
                            borderWidth: 3,
                            pointRadius: 4,
                            pointBackgroundColor: '#2f9e44',
                            pointHoverRadius: 5,
                            tension: 0.42
                        },
                        {
                            label: 'Absent',
                            data: absentSeries,
                            borderColor: '#f08c00',
                            backgroundColor: gradientAbsent,
                            fill: true,
                            borderWidth: 3,
                            pointRadius: 4,
                            pointBackgroundColor: '#f08c00',
                            pointHoverRadius: 5,
                            tension: 0.42
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    animation: {
                        duration: 1300,
                        easing: 'easeOutQuart'
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#2f3f24',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#b5d87a',
                            borderWidth: 1,
                            padding: 10
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: '#6f7765',
                                font: { size: 11, weight: 600 }
                            },
                            border: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            suggestedMax: 100,
                            ticks: {
                                color: '#7b8368',
                                stepSize: 20,
                                font: { size: 11, weight: 600 },
                                callback: function (value) {
                                    return value + '%';
                                }
                            },
                            grid: {
                                color: '#edf7e8',
                                drawTicks: false
                            },
                            border: { display: false }
                        }
                    }
                }
            });
        });
    </script>
@endpush
