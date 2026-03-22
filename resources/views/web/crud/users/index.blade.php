@extends('web.layouts.app')

@section('content')
    @php
        $resolveImage = static function (?string $value): string {
            $url = trim((string) $value);
            if ($url === '') {
                return '';
            }

            if (\Illuminate\Support\Str::startsWith($url, ['http://', 'https://', '/', 'data:'])) {
                return $url;
            }

            return asset($url);
        };

        $selectedSchoolId = (string) ($filters['school_id'] ?? '');
        $schoolSelectOptions = collect($schoolOptions ?? []);

        if ($selectedSchoolId !== '' && ! $schoolSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedSchoolId)) {
            $schoolSelectOptions = $schoolSelectOptions->prepend([
                'id' => (int) $selectedSchoolId,
                'label' => 'School ID: '.$selectedSchoolId,
            ]);
        }

        $selectedClassId = (string) ($filters['class_id'] ?? '');
        $classSelectOptions = collect($classOptions ?? []);

        if ($selectedClassId !== '' && ! $classSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedClassId)) {
            $classSelectOptions = $classSelectOptions->prepend([
                'id' => (int) $selectedClassId,
                'label' => 'Class ID: '.$selectedClassId,
            ]);
        }

        $itemsCollection = collect($items ?? []);
        $visibleCount = $itemsCollection->count();
        $totalCount = (int) ($meta['total'] ?? $visibleCount);
        $activeCount = $itemsCollection->filter(fn ($item) => !empty($item['active']))->count();
        $verifiedCount = $itemsCollection->filter(fn ($item) => !empty($item['email_verified_at']))->count();
        $studentCount = $itemsCollection->filter(fn ($item) => ($item['role'] ?? null) === 'student')->count();
        $teacherCount = $itemsCollection->filter(fn ($item) => ($item['role'] ?? null) === 'teacher')->count();
        $parentCount = $itemsCollection->filter(fn ($item) => ($item['role'] ?? null) === 'parent')->count();
        $currentPage = (int) ($meta['current_page'] ?? 1);
        $lastPage = (int) ($meta['last_page'] ?? 1);
        $showingAll = ($filters['per_page'] ?? '20') === 'all';
        $activeFilters = array_filter([
            (isset($filters['user_id']) && $filters['user_id'] !== null && $filters['user_id'] !== '') ? 'User ID: '.$filters['user_id'] : null,
            $selectedSchoolId !== '' ? 'School: '.$selectedSchoolId : null,
            $selectedClassId !== '' ? 'Class: '.$selectedClassId : null,
            !empty($filters['role']) ? 'Role: '.str_replace('-', ' ', (string) $filters['role']) : null,
            isset($filters['active']) && $filters['active'] !== '' ? 'Active: '.((string) $filters['active'] === '1' ? 'Yes' : 'No') : null,
            !empty($filters['search']) ? 'Search: '.$filters['search'] : null,
        ]);

        $roleTone = static function (?string $role): string {
            return match ($role) {
                'super-admin' => 'role-super',
                'admin' => 'role-admin',
                'teacher' => 'role-teacher',
                'student' => 'role-student',
                'parent' => 'role-parent',
                default => 'role-default',
            };
        };
    @endphp

    <style>
        .users-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(280px, 0.8fr);
            gap: 14px;
            margin-bottom: 16px;
        }

        .users-hero-main {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(15, 118, 110, 0.12);
            border-radius: 22px;
            padding: 22px;
            background:
                radial-gradient(circle at top right, rgba(96, 165, 250, 0.16), transparent 36%),
                linear-gradient(135deg, rgba(15, 118, 110, 0.08), rgba(255, 255, 255, 0.96));
            box-shadow: var(--shadow-sm);
        }

        .users-hero-main::after {
            content: "";
            position: absolute;
            right: -48px;
            bottom: -58px;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(15, 118, 110, 0.08);
        }

        .users-hero-title {
            margin: 0;
            font-size: clamp(30px, 3vw, 40px);
            line-height: 1.05;
        }

        .users-hero-copy {
            margin: 10px 0 0;
            max-width: 760px;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .users-hero-tags,
        .users-filter-tags,
        .users-role-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .users-hero-tags {
            margin-top: 16px;
        }

        .users-filter-tags {
            margin-top: 12px;
        }

        .hero-pill,
        .filter-pill,
        .status-pill,
        .role-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.2px;
            white-space: nowrap;
        }

        .hero-pill {
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(15, 118, 110, 0.14);
            color: var(--primary-2);
        }

        .filter-pill {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
        }

        .users-hero-side {
            border-radius: 22px;
            padding: 18px;
            color: #fff;
            background: linear-gradient(145deg, #134e4a, #0f766e 54%, #2b6cb0);
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
        }

        .users-hero-side h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }

        .users-hero-side p {
            margin: 6px 0 0;
            color: rgba(255, 255, 255, 0.86);
            font-size: 13px;
            line-height: 1.6;
        }

        .users-quick-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .users-quick-stat {
            border-radius: 16px;
            padding: 14px 12px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(4px);
        }

        .users-quick-stat strong {
            display: block;
            font-size: 24px;
            line-height: 1;
        }

        .users-quick-stat span {
            display: block;
            margin-top: 6px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.82);
        }

        .users-panel-stack {
            display: grid;
            gap: 12px;
            margin-bottom: 12px;
        }

        .users-filter-panel {
            background: linear-gradient(180deg, #ffffff 0%, #f9fcfb 100%);
        }

        .users-panel-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .users-panel-head h3 {
            margin: 0;
            font-size: 16px;
        }

        .users-panel-head p {
            margin: 4px 0 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .users-summary-badge {
            border-radius: 999px;
            padding: 8px 12px;
            background: #eefbf6;
            border: 1px solid #c8f2de;
            color: #0f766e;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .users-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 14px 16px 0;
        }

        .users-toolbar-actions,
        .users-toolbar-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .users-toolbar-meta {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 700;
        }

        .users-toolbar button,
        .toolbar-link,
        .mini-btn {
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 8px 12px;
            background: #fff;
            color: var(--primary-2);
            font-size: 11px;
            font-weight: 800;
            text-decoration: none;
            box-shadow: none;
            transform: none;
        }

        .users-toolbar button:hover,
        .toolbar-link:hover,
        .mini-btn:hover {
            background: var(--surface-soft);
            border-color: #b8d4cc;
            box-shadow: none;
            transform: none;
        }

        .toolbar-button-danger,
        .mini-btn-danger {
            color: #be123c;
            border-color: #fecdd3;
            background: #fff1f2;
        }

        .toolbar-button-danger:hover,
        .mini-btn-danger:hover {
            background: #ffe4e6;
            border-color: #fda4af;
        }

        .users-table-wrap {
            overflow: auto;
        }

        .users-table {
            min-width: 1240px;
        }

        .users-table tbody tr {
            transition: background-color .18s ease, transform .18s ease;
        }

        .users-table tbody tr:hover {
            transform: translateY(-1px);
        }

        .users-table .col-select {
            width: 44px;
            text-align: center;
        }

        .users-table .col-photo {
            width: 84px;
        }

        .mini-actions .inline-form {
            margin-left: 0;
        }

        .users-avatar-shell {
            width: 62px;
            height: 62px;
            border-radius: 16px;
            border: 1px solid #dce8e3;
            background: linear-gradient(135deg, #f1f5f9, #f8fafc);
            display: grid;
            place-items: center;
            color: #64748b;
            font-size: 18px;
            font-weight: 800;
        }

        .users-name-cell {
            min-width: 220px;
        }

        .users-name-primary {
            font-size: 15px;
            font-weight: 800;
            color: #122620;
        }

        .users-name-secondary {
            margin-top: 4px;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .users-name-secondary strong {
            color: #36514a;
            font-weight: 700;
        }

        .users-email-cell,
        .users-school-cell,
        .users-class-cell {
            min-width: 170px;
        }

        .users-main-line {
            font-weight: 700;
            color: #18312a;
            line-height: 1.5;
        }

        .users-sub-line {
            margin-top: 4px;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .status-pill {
            justify-content: center;
            border: 1px solid transparent;
        }

        .status-success {
            background: #ecfdf5;
            border-color: #bbf7d0;
            color: #15803d;
        }

        .status-warning {
            background: #fff7ed;
            border-color: #fed7aa;
            color: #c2410c;
        }

        .status-neutral {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #475569;
        }

        .role-chip {
            border: 1px solid transparent;
            text-transform: capitalize;
        }

        .role-super {
            background: #ecfeff;
            border-color: #a5f3fc;
            color: #155e75;
        }

        .role-admin {
            background: #eef2ff;
            border-color: #c7d2fe;
            color: #4338ca;
        }

        .role-teacher {
            background: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }

        .role-student {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }

        .role-parent {
            background: #fff7ed;
            border-color: #fed7aa;
            color: #c2410c;
        }

        .role-default {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #475569;
        }

        .users-empty {
            padding: 42px 18px;
            text-align: center;
            color: var(--text-muted);
        }

        .users-empty strong {
            display: block;
            margin-bottom: 8px;
            color: #16322c;
            font-size: 16px;
        }

        @media (max-width: 1120px) {
            .users-hero {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .users-quick-stats {
                grid-template-columns: 1fr;
            }

            .users-panel-head,
            .users-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .users-toolbar-actions,
            .users-toolbar-meta {
                width: 100%;
            }
        }
    </style>

    <section class="users-hero">
        <div class="users-hero-main">
            <h1 class="users-hero-title">User Management</h1>
            <p class="users-hero-copy">
                គ្រប់គ្រងអ្នកប្រើប្រាស់តាម role និង school scope បានងាយជាងមុន។ ទំព័រនេះបង្ហាញស្ថានភាពសំខាន់ៗ,
                filter ដែលកំពុងប្រើ, និង action ដែលប្រើញឹកញាប់នៅកន្លែងតែមួយ។
            </p>
            <div class="users-hero-tags">
                <span class="hero-pill">Total {{ number_format($totalCount) }} users</span>
                <span class="hero-pill">Showing {{ number_format($visibleCount) }} on this page</span>
                <span class="hero-pill">Page {{ $currentPage }} / {{ max($lastPage, 1) }}</span>
                <span class="hero-pill">{{ $showingAll ? 'View All enabled' : 'Optimized for 20 per page' }}</span>
            </div>
            @if ($activeFilters !== [])
                <div class="users-filter-tags">
                    @foreach ($activeFilters as $filterLabel)
                        <span class="filter-pill">{{ $filterLabel }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="users-hero-side">
            <div>
                <h3>Quick snapshot</h3>
                <p>សង្ខេបចំនួន user ដែលកំពុងបង្ហាញក្នុង view នេះ ដើម្បីងាយតាមដានមុនធ្វើ action.</p>
            </div>
            <div class="users-quick-stats">
                <div class="users-quick-stat">
                    <strong>{{ number_format($activeCount) }}</strong>
                    <span>Active</span>
                </div>
                <div class="users-quick-stat">
                    <strong>{{ number_format($verifiedCount) }}</strong>
                    <span>Verified</span>
                </div>
                <div class="users-quick-stat">
                    <strong>{{ number_format($studentCount + $teacherCount + $parentCount) }}</strong>
                    <span>Main roles in view</span>
                </div>
            </div>
            <div class="users-role-pills">
                <span class="role-chip role-teacher">Teacher {{ number_format($teacherCount) }}</span>
                <span class="role-chip role-student">Student {{ number_format($studentCount) }}</span>
                <span class="role-chip role-parent">Parent {{ number_format($parentCount) }}</span>
            </div>
        </div>
    </section>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        <a href="{{ route('panel.users.index', ['per_page' => 'all'] + array_filter($filters, fn ($value) => $value !== null && $value !== '')) }}">View All Users</a>
        @can('web-manage-users')
            <a href="{{ route('panel.users.create') }}" class="active">+ Create User</a>
        @endcan
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <div class="users-panel-stack">
        <form method="POST" action="{{ route('panel.users.import-csv') }}" class="panel panel-form panel-spaced" enctype="multipart/form-data">
            @csrf
            <div class="users-panel-head">
                <div>
                    <h3>Import Teacher / Student / Parent CSV</h3>
                    <p>បញ្ចូល user ច្រើនក្នុងពេលតែមួយដោយប្រើ template ថ្មីដែលមាន fields សំខាន់ៗតែប៉ុណ្ណោះ។</p>
                </div>
                <span class="users-summary-badge">CSV Import</span>
            </div>
            <div class="upload-shell btn-space-top">
                <div class="upload-head">
                    <p class="upload-note">Use the new CSV format. Required columns: <strong>role</strong>, <strong>first_name</strong>, <strong>last_name</strong>, <strong>khmer_name</strong>, <strong>phone</strong>, <strong>email</strong>. Student class/ID can be added later.</p>
                    <span class="badge-soft">CSV</span>
                </div>
                <label class="upload-zone" data-upload-zone>
                    <input id="users_csv_file" type="file" name="csv_file" accept=".csv,text/csv" required>
                    <span class="upload-title">Drop CSV file here or click to browse</span>
                    <span class="upload-meta" data-upload-meta>No file selected</span>
                </label>
                <div class="upload-hints">
                    <span>UTF-8</span>
                    <span>Teacher + Student + Parent</span>
                    <span>Minimal first import fields</span>
                    <span>class / grade / student_id optional</span>
                    <span><a href="{{ asset('templates/user_import_template.csv') }}" download>Download template</a></span>
                </div>
            </div>
            <div class="form-grid btn-space-top">
                <select name="role">
                    <option value="teacher">Default Role: Teacher</option>
                    <option value="student">Default Role: Student</option>
                    <option value="parent">Default Role: Parent</option>
                </select>
                @if($userRole === 'super-admin')
                    <input type="number" name="school_id" placeholder="Default school_id (optional)">
                @else
                    <input type="text" value="School auto-detect by your admin account" disabled>
                @endif
            </div>
            <button type="submit" class="btn-space-top">Upload CSV</button>
        </form>

        <form method="GET" action="{{ route('panel.users.index') }}" class="panel panel-form panel-spaced users-filter-panel">
            <div class="users-panel-head">
                <div>
                    <h3>Filter and search users</h3>
                    <p>ស្វែងរកតាមឈ្មោះ, email, phone, code ឬបំបែកតាម role និង class បានលឿនជាងមុន។</p>
                </div>
                <span class="users-summary-badge">{{ $showingAll ? 'Viewing all users' : '20 users per page' }}</span>
            </div>
            <div class="form-grid btn-space-top">
                @if($userRole === 'super-admin')
                    <div>
                        <input type="text" class="searchable-select-search" placeholder="Search school..." data-select-search-for="filter_school_id">
                        <select id="filter_school_id" name="school_id">
                            <option value="">School</option>
                            @foreach($schoolSelectOptions as $option)
                                <option value="{{ $option['id'] }}" {{ $selectedSchoolId === (string) $option['id'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <input type="number" name="user_id" placeholder="User ID" value="{{ $filters['user_id'] ?? '' }}">
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
                <select name="role">
                    <option value="">Role</option>
                    @foreach (['super-admin', 'admin', 'teacher', 'student', 'parent'] as $role)
                        <option value="{{ $role }}" {{ ($filters['role'] ?? '') === $role ? 'selected' : '' }}>{{ $role }}</option>
                    @endforeach
                </select>
                <select name="active">
                    <option value="">Active?</option>
                    <option value="1" {{ ($filters['active'] ?? '') === '1' ? 'selected' : '' }}>Yes</option>
                    <option value="0" {{ ($filters['active'] ?? '') === '0' ? 'selected' : '' }}>No</option>
                </select>
                <input type="text" name="search" placeholder="Name/Username/Email/Phone/Code" value="{{ $filters['search'] ?? '' }}">
                @php
                    $perPageValue = (string) ($filters['per_page'] ?? '20');
                @endphp
                <select name="per_page">
                    <option value="20" {{ $perPageValue === '20' ? 'selected' : '' }}>មើលម្ដង 20</option>
                    <option value="all" {{ $perPageValue === 'all' ? 'selected' : '' }}>View All</option>
                </select>
            </div>
            <div class="btn-space-top" style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <button type="submit">Apply Filters</button>
                <a href="{{ route('panel.users.index') }}" class="toolbar-link">Clear</a>
            </div>
        </form>
    </div>

    <section class="panel">
        <div class="panel-head">
            <div class="users-panel-head">
                <div>
                    <h3>Users Directory</h3>
                    <p>
                        Showing {{ number_format($visibleCount) }}
                        of {{ number_format($totalCount) }} users
                        @if (! $showingAll)
                            on this page
                        @endif
                    </p>
                </div>
                <span class="users-summary-badge">{{ $showingAll ? 'Showing all users' : 'Page '.$currentPage.' of '.max($lastPage, 1) }}</span>
            </div>
        </div>
        <form id="bulk-delete-users-form" action="{{ route('panel.users.bulk-delete') }}" method="POST">
            @csrf
        </form>
        <div class="users-toolbar">
            <div class="users-toolbar-actions">
                <button type="submit" form="bulk-delete-users-form" class="toolbar-button-danger" onclick="return confirm('Delete selected users?')">Delete Selected</button>
                <button type="button" data-select-all-users>Select Visible</button>
                <button type="button" data-clear-users-selection>Clear Selection</button>
            </div>
            <div class="users-toolbar-meta">
                <span data-selected-users-count>0 selected</span>
                <span>•</span>
                <span>{{ $showingAll ? 'Full result set loaded' : 'Performance mode enabled' }}</span>
            </div>
        </div>
        <div class="users-table-wrap">
        <table class="users-table">
            <thead>
            <tr>
                <th class="col-select">
                    <input type="checkbox" data-toggle-users-selection aria-label="Select all visible users">
                </th>
                <th>ID</th>
                <th>Code</th>
                <th class="col-photo">Photo</th>
                <th>Name</th>
                <th>Email</th>
                <th>Verified</th>
                <th>Role</th>
                <th>School</th>
                <th>Class</th>
                <th>Active</th>
                <th>Student Profile</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td class="col-select">
                        <input
                            type="checkbox"
                            name="user_ids[]"
                            value="{{ $item['id'] }}"
                            form="bulk-delete-users-form"
                            data-user-selection
                            aria-label="Select user {{ $item['name'] }}"
                        >
                    </td>
                    <td>{{ $item['id'] }}</td>
                    <td>{{ $item['user_code'] ?? '-' }}</td>
                    <td class="col-photo">
                        @if(!empty($item['image_url']))
                            <img src="{{ $resolveImage($item['image_url']) }}" alt="User image" class="avatar-list">
                        @else
                            <div class="users-avatar-shell">
                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) ($item['first_name'] ?? $item['name'] ?? 'U'), 0, 1)) }}
                            </div>
                        @endif
                    </td>
                    <td class="users-name-cell">
                        <div class="users-name-primary">{{ $item['name'] ?: '-' }}</div>
                        <div class="users-name-secondary">
                            @if (!empty($item['khmer_name']))
                                <div><strong>KH:</strong> {{ $item['khmer_name'] }}</div>
                            @endif
                            <div><strong>Phone:</strong> {{ $item['phone'] ?? '-' }}</div>
                        </div>
                    </td>
                    <td class="users-email-cell">
                        <div class="users-main-line">{{ $item['email'] ?: '-' }}</div>
                        <div class="users-sub-line">
                            Username: {{ $item['username'] ?? '-' }}<br>
                            Code: {{ $item['user_code'] ?? '-' }}
                        </div>
                    </td>
                    <td>
                        <span class="status-pill {{ !empty($item['email_verified_at']) ? 'status-success' : 'status-warning' }}">
                            {{ !empty($item['email_verified_at']) ? 'Verified' : 'Pending' }}
                        </span>
                    </td>
                    <td>
                        <span class="role-chip {{ $roleTone($item['role'] ?? null) }}">
                            {{ str_replace('-', ' ', (string) ($item['role'] ?? 'unknown')) }}
                        </span>
                    </td>
                    <td class="users-school-cell">
                        <div class="users-main-line">{{ $item['school']['name'] ?? ($item['school_id'] ?? '-') }}</div>
                        <div class="users-sub-line">School ID: {{ $item['school_id'] ?? '-' }}</div>
                    </td>
                    <td class="users-class-cell">
                        <div class="users-main-line">{{ $item['student_profile']['class']['name'] ?? ($item['student_profile']['class_id'] ?? '-') }}</div>
                        <div class="users-sub-line">
                            Student ID: {{ $item['student_profile']['student_id'] ?? '-' }}
                        </div>
                    </td>
                    <td>
                        <span class="status-pill {{ !empty($item['active']) ? 'status-success' : 'status-neutral' }}">
                            {{ !empty($item['active']) ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        <span class="status-pill {{ !empty($item['student_profile']) ? 'status-success' : 'status-neutral' }}">
                            {{ !empty($item['student_profile']) ? 'Linked' : 'No profile' }}
                        </span>
                    </td>
                    <td>
                        @can('web-manage-users')
                        <div class="mini-actions">
                            <a href="{{ route('panel.users.show', $item['id']) }}">View</a>
                            <a href="{{ route('panel.users.edit', $item['id']) }}">Edit</a>

                            @if (empty($item['email_verified_at']))
                                <form action="{{ route('panel.users.resend-verification', $item['id']) }}" method="POST" class="inline-form">
                                    @csrf
                                    <button type="submit" class="mini-btn">Resend Verify</button>
                                </form>
                            @endif

                            <form action="{{ route('panel.users.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Delete this user?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="mini-btn mini-btn-danger">Delete</button>
                            </form>
                        </div>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="users-empty">
                        <strong>No users found</strong>
                        Try changing filters or clear the current search to load more users.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </section>

    <script>
        (() => {
            const checkboxes = Array.from(document.querySelectorAll('[data-user-selection]'));
            const toggleAll = document.querySelector('[data-toggle-users-selection]');
            const selectVisible = document.querySelector('[data-select-all-users]');
            const clearSelection = document.querySelector('[data-clear-users-selection]');
            const counter = document.querySelector('[data-selected-users-count]');
            const bulkForm = document.getElementById('bulk-delete-users-form');

            if (! bulkForm || checkboxes.length === 0 || ! toggleAll || ! selectVisible || ! clearSelection || ! counter) {
                if (counter) {
                    counter.textContent = '0 selected';
                }

                return;
            }

            const syncSelectionState = () => {
                const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
                counter.textContent = `${selected} selected`;
                toggleAll.checked = selected > 0 && selected === checkboxes.length;
                toggleAll.indeterminate = selected > 0 && selected < checkboxes.length;
            };

            toggleAll.addEventListener('change', () => {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = toggleAll.checked;
                });

                syncSelectionState();
            });

            selectVisible.addEventListener('click', () => {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = true;
                });

                syncSelectionState();
            });

            clearSelection.addEventListener('click', () => {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = false;
                });

                syncSelectionState();
            });

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', syncSelectionState);
            });

            bulkForm.addEventListener('submit', (event) => {
                const hasSelection = checkboxes.some((checkbox) => checkbox.checked);

                if (! hasSelection) {
                    event.preventDefault();
                    alert('Please select at least one user to delete.');
                }
            });

            syncSelectionState();
        })();
    </script>
@endsection
