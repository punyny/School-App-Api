<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $pageTitle = trim((string) ($title ?? ''));
        $appName = trim((string) config('app.name', 'Sala Digital'));
        $browserTitle = $pageTitle !== ''
            ? $pageTitle.' | '.$appName
            : $appName;
    @endphp
    <title>{{ $browserTitle }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('sala-digital-mark.svg') }}">
    <link rel="shortcut icon" href="{{ asset('sala-digital-mark.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Kantumruy+Pro:wght@400;500;600;700&family=Noto+Sans+Khmer:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-page-1: #f6f1e6;
            --bg-page-2: #e9f2f4;
            --frame-bg: rgba(255, 255, 255, 0.94);
            --surface: #ffffff;
            --surface-soft: #f2f6f4;
            --line: #d9e2de;
            --text-main: #182620;
            --text-muted: #5b6b65;
            --primary: #0f766e;
            --primary-2: #0b4c45;
            --accent-blue: #2b6cb0;
            --accent-orange: #f97316;
            --accent-green: #22c55e;
            --danger: #e11d48;
            --shadow-lg: 0 26px 60px rgba(17, 47, 42, 0.18);
            --shadow-sm: 0 10px 22px rgba(22, 42, 38, 0.10);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Sora", "Kantumruy Pro", "Noto Sans Khmer", sans-serif;
            color: var(--text-main);
            background:
                radial-gradient(900px 420px at -12% -18%, #dbeafe 0%, transparent 72%),
                radial-gradient(980px 460px at 112% 115%, #fde68a 0%, transparent 70%),
                linear-gradient(145deg, var(--bg-page-1) 0%, #fff1df 35%, var(--bg-page-2) 100%);
        }

        body.sidebar-mobile-lock {
            overflow: hidden;
        }

        body.locale-km {
            font-family: "Kantumruy Pro", "Noto Sans Khmer", "Sora", sans-serif;
            letter-spacing: 0;
        }

        body.locale-km .global-sidebar h3,
        body.locale-km .panel-head,
        body.locale-km label,
        body.locale-km th,
        body.locale-km .card .label,
        body.locale-km .role-pill {
            text-transform: none;
            letter-spacing: 0;
        }

        body.locale-km .title {
            line-height: 1.3;
            letter-spacing: 0;
        }

        body.locale-km .subtitle,
        body.locale-km .text-muted,
        body.locale-km td,
        body.locale-km li,
        body.locale-km p {
            line-height: 1.7;
        }

        body.locale-km input,
        body.locale-km select,
        body.locale-km textarea,
        body.locale-km button {
            font-size: 14px;
        }

        .shell {
            max-width: 1580px;
            margin: 0 auto;
            padding: 28px;
        }

        .app-frame {
            background: var(--frame-bg);
            border: 1px solid rgba(255, 255, 255, 0.55);
            border-radius: 26px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            backdrop-filter: blur(8px);
        }

        .workspace-shell {
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
            min-height: calc(100vh - 56px);
            transition: grid-template-columns .22s ease;
        }

        .global-sidebar {
            border-right: 1px solid var(--line);
            background: linear-gradient(180deg, #ffffff 0%, #f6faf9 100%);
            padding: 20px 16px;
            overflow-y: auto;
            transition: padding .22s ease;
        }

        .sidebar-backdrop {
            display: none;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: inherit;
            padding: 4px 8px 16px;
            margin-bottom: 8px;
            border-bottom: 1px solid var(--line);
        }

        .brand-mark {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            box-shadow: 0 8px 22px rgba(12, 80, 72, 0.32);
            overflow: hidden;
            padding: 7px;
        }

        .brand-mark img {
            width: 100%;
            height: 100%;
            display: block;
        }

        .brand-name {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.2px;
        }

        .brand-sub {
            margin: 2px 0 0;
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .global-sidebar h3 {
            margin: 12px 8px 8px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6d765f;
        }

        .sidebar-links {
            display: grid;
            gap: 5px;
            margin-bottom: 10px;
        }

        .sidebar-links a {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: #4f615a;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid transparent;
            border-radius: 11px;
            padding: 11px 12px;
            transition: .18s ease;
        }

        .sidebar-links a:hover {
            background: var(--surface-soft);
            border-color: #c7d7d1;
            color: var(--primary-2);
        }

        .sidebar-links a.active {
            background: linear-gradient(135deg, #f1f6f5 0%, var(--surface-soft) 100%);
            border-color: #c9d9d3;
            color: var(--primary-2);
            box-shadow: inset 2px 0 0 var(--primary);
        }

        .nav-icon {
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            color: #73847e;
        }

        .nav-icon svg {
            width: 22px;
            height: 22px;
            stroke-width: 2.1;
        }

        .sidebar-links a.active .nav-icon,
        .sidebar-links a:hover .nav-icon {
            color: var(--primary-2);
        }

        .nav-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .workspace-main {
            min-width: 0;
            padding: 22px;
            overflow: auto;
        }

        .head-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 0;
        }

        .sidebar-toggle {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid #dcd2fa;
            background: #fff;
            color: var(--primary-2);
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            cursor: pointer;
            transition: .18s ease;
        }

        .sidebar-toggle:hover {
            background: var(--surface-soft);
            border-color: #c7d7d1;
            box-shadow: none;
            transform: none;
        }

        .global-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
            padding: 14px;
            border-radius: 16px;
            border: 1px solid var(--line);
            background: #fff;
            box-shadow: var(--shadow-sm);
        }

        .school-standard-strip {
            margin: 0 0 14px;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 10px 12px;
            background:
                linear-gradient(100deg, #f7fbf9 0%, #fff2e3 56%, #f1fbf7 100%);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .school-standard-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            min-width: 0;
        }

        .school-standard-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border: 1px solid #d0e0da;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.92);
            color: #3c5b52;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .school-standard-pill-link {
            text-decoration: none;
            transition: .16s ease;
        }

        .school-standard-pill-link:hover {
            border-color: #b9d0c7;
            background: #f0f9f6;
            color: var(--primary-2);
        }

        .school-standard-pill svg {
            width: 15px;
            height: 15px;
            stroke-width: 2.1;
        }

        .school-standard-date {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #51625b;
            font-size: 12px;
            font-weight: 700;
            flex-wrap: nowrap;
            white-space: nowrap;
        }

        .school-standard-date svg {
            width: 15px;
            height: 15px;
            stroke-width: 2.1;
        }

        .workflow-nav {
            margin: 0 0 16px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }

        .workflow-link {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fff;
            text-decoration: none;
            color: #3f5751;
            padding: 13px;
            display: grid;
            gap: 6px;
            transition: .18s ease;
            box-shadow: var(--shadow-sm);
        }

        .workflow-link:hover {
            border-color: #b8d4cc;
            background: var(--surface-soft);
            transform: translateY(-1px);
        }

        .workflow-link.active {
            border-color: #c0d6cf;
            background: linear-gradient(135deg, #f1f6f5 0%, var(--surface-soft) 100%);
            box-shadow: inset 3px 0 0 var(--primary), var(--shadow-sm);
        }

        .workflow-step {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            color: #fff;
            font-size: 12px;
            font-weight: 800;
        }

        .workflow-title {
            font-size: 14px;
            font-weight: 800;
            color: #26433a;
        }

        .workflow-desc {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
            line-height: 1.5;
        }

        .global-search {
            flex: 1;
            min-width: 180px;
            max-width: 460px;
            position: relative;
        }

        .global-search input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--surface-soft);
            padding: 10px 38px 10px 14px;
            color: var(--text-main);
            font-size: 13px;
            font-weight: 500;
        }

        .global-search::after {
            content: "⌕";
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 16px;
            pointer-events: none;
        }

        .head-tools {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .role-pill {
            border: 1px solid #dbe8be;
            background: #f5fbe6;
            color: var(--primary-2);
            padding: 6px 11px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.45px;
        }

        .user-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 7px 10px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            max-width: 260px;
        }

        .user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--accent-blue), var(--primary));
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            flex: 0 0 auto;
        }

        .user-chip .name {
            display: block;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-chip .email {
            display: block;
            font-size: 10px;
            color: var(--text-muted);
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .head-link {
            text-decoration: none;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--text-main);
            border-radius: 10px;
            padding: 8px 11px;
            font-size: 12px;
            font-weight: 600;
            transition: .2s ease;
        }

        .head-link:hover {
            border-color: #b8d4cc;
            color: var(--primary-2);
            background: var(--surface-soft);
        }

        .locale-inline-form select {
            border: 1px solid var(--line);
            background: #fff;
            color: var(--text-main);
            border-radius: 10px;
            padding: 8px 11px;
            font-size: 12px;
            font-weight: 600;
        }

        .logout {
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent-orange), #ea580c);
            color: #fff;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .logout:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 18px rgba(234, 88, 12, 0.26);
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }

        .title {
            margin: 0;
            font-size: clamp(26px, 2.5vw, 36px);
            letter-spacing: -.25px;
            line-height: 1.15;
        }

        .subtitle {
            margin: 7px 0 0;
            color: var(--text-muted);
            font-size: 15px;
            line-height: 1.5;
        }

        .subtitle-tight {
            margin-top: 6px;
        }

        .nav {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .nav a {
            text-decoration: none;
            border: 1px solid var(--line);
            background: #fff;
            color: #5d684d;
            border-radius: 10px;
            padding: 10px 13px;
            font-size: 13px;
            font-weight: 600;
            transition: .18s ease;
        }

        .nav a:hover {
            border-color: #b7c9c2;
            color: var(--primary-2);
            background: var(--surface-soft);
        }

        .nav a.active {
            color: #fff;
            border-color: transparent;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            box-shadow: 0 10px 18px rgba(12, 80, 72, 0.26);
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin: 0 0 16px;
        }

        .card {
            border-radius: 14px;
            border: 1px solid var(--line);
            background: #fff;
            padding: 16px 70px 16px 16px;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: linear-gradient(180deg, var(--primary), var(--accent-blue));
        }

        .card .label {
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .45px;
            font-weight: 700;
            margin-left: 4px;
        }

        .card .value {
            margin: 8px 0 0 4px;
            font-size: 30px;
            line-height: 1;
            font-weight: 800;
            color: #1e3a32;
        }

        .card-icon {
            position: absolute;
            right: 12px;
            top: 10px;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #e8f7f3, #f2fbf9);
            border: 1px solid #d6e6e1;
            color: var(--primary);
            box-shadow: 0 8px 16px rgba(20, 78, 70, 0.18);
        }

        .card-icon svg {
            width: 24px;
            height: 24px;
            stroke-width: 2.2;
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            box-shadow: var(--shadow-sm);
            overflow: auto;
        }

        .panel-head {
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .5px;
            font-weight: 700;
            color: #465a53;
            background: linear-gradient(120deg, #ffffff 0%, var(--surface-soft) 100%);
        }

        .panel-form {
            padding: 16px;
        }

        .panel-spaced {
            margin-bottom: 12px;
        }

        .empty {
            padding: 14px;
            color: var(--text-muted);
            font-size: 13px;
        }

        .flash-success,
        .alert-success {
            margin: 0 0 12px;
            padding: 11px 12px;
            border-radius: 12px;
            border: 1px solid #c5f2da;
            background: #ebfff4;
            color: #156f49;
            font-size: 12px;
            font-weight: 700;
        }

        .flash-error,
        .alert-error {
            margin: 0 0 12px;
            padding: 11px 12px;
            border-radius: 12px;
            border: 1px solid #ffd6df;
            background: #fff1f4;
            color: #a23d58;
            font-size: 12px;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
            font-size: 14px;
        }

        th,
        td {
            text-align: left;
            padding: 12px 13px;
            border-bottom: 1px solid #edf7e8;
            vertical-align: top;
        }

        th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: var(--surface-soft);
            color: #6a7872;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .55px;
            font-weight: 700;
        }

        tbody tr:nth-child(even) {
            background: #f8fbfa;
        }

        tbody tr:hover {
            background: var(--surface-soft);
        }

        .score-matrix-wrap {
            overflow: auto;
            border: 2px solid #d7d7d7;
            border-radius: 8px;
            background: #fff;
        }

        table.score-matrix {
            border-collapse: separate;
            border-spacing: 0;
            min-width: 900px;
            background: #f9f9f9;
        }

        table.score-matrix th,
        table.score-matrix td {
            border: 2px solid #d9d9d9;
            padding: 14px 12px;
            vertical-align: middle;
            background: #fff;
        }

        table.score-matrix th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #ffffff;
            color: #2d3a34;
            font-size: 13px;
            letter-spacing: .1px;
            text-transform: none;
            font-weight: 700;
            text-align: center;
        }

        table.score-matrix td {
            text-align: center;
        }

        table.score-matrix td.score-matrix-student,
        table.score-matrix th.score-matrix-student {
            text-align: left;
            min-width: 220px;
        }

        table.score-matrix td.score-matrix-student strong {
            font-size: 14px;
            font-weight: 700;
            color: #2b2b2b;
        }

        table.score-matrix th.score-matrix-subject {
            min-width: 140px;
        }

        .score-matrix-input {
            width: 100%;
            max-width: 90px;
            margin: 0 auto;
            text-align: center;
            border: 1px solid #a9a9a9;
            border-radius: 6px;
            padding: 8px 10px;
            font-weight: 600;
            background: #fff;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.08);
        }

        .score-matrix-input:focus {
            border-color: #2f9e44;
            box-shadow: 0 0 0 3px rgba(47, 158, 68, 0.14);
        }

        td a {
            color: var(--primary-2);
            text-decoration: none;
            font-weight: 700;
        }

        td a:hover {
            text-decoration: underline;
        }

        form.inline-form {
            display: inline-flex;
            margin-left: 6px;
            vertical-align: middle;
        }

        label {
            display: block;
            margin: 11px 0 6px;
            color: #64746f;
            font-size: 12px;
            letter-spacing: .45px;
            text-transform: uppercase;
            font-weight: 700;
        }

        .label-tight {
            margin-top: 0;
        }

        input,
        select,
        textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 11px;
            background: #fff;
            color: var(--text-main);
            padding: 12px 13px;
            font-size: 14px;
            font-family: inherit;
            transition: .18s ease;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #58b5a8;
            box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.14);
        }

        input[type="checkbox"],
        input[type="radio"] {
            width: 16px;
            height: 16px;
            padding: 0;
            border: none;
            border-radius: 4px;
            background: transparent;
            box-shadow: none;
            accent-color: var(--accent-blue);
            flex: 0 0 auto;
            vertical-align: middle;
        }

        input[type="checkbox"]:focus,
        input[type="radio"]:focus {
            outline: 2px solid rgba(43, 108, 176, 0.22);
            outline-offset: 2px;
            border-color: transparent;
            box-shadow: none;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        button {
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            color: #fff;
            padding: 11px 14px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: .18s ease;
        }

        button,
        .nav a,
        .head-link,
        .sidebar-links a {
            min-height: 44px;
        }

        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 16px rgba(12, 80, 72, 0.26);
        }

        button:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-space-top {
            margin-top: 12px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 10px;
        }

        .form-grid-wide {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 10px;
        }

        .searchable-select-wrap {
            margin-top: 6px;
        }

        .searchable-select-search {
            margin-bottom: 6px;
            background: var(--surface-soft);
            font-size: 12px;
        }

        .upload-shell {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px;
            background: #f7fbf9;
        }

        .upload-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 8px;
        }

        .upload-note {
            margin: 0;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.45;
            font-weight: 600;
        }

        .badge-soft {
            border: 1px solid #f5d0a4;
            background: #fff2e1;
            color: #9a4b12;
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .4px;
        }

        .upload-zone {
            display: grid;
            justify-items: center;
            gap: 5px;
            text-align: center;
            padding: 16px 12px;
            border-radius: 12px;
            border: 2px dashed #b8d4cc;
            background: #fff;
            cursor: pointer;
            transition: .2s ease;
        }

        .upload-zone:hover,
        .upload-zone.dragover {
            border-color: var(--primary);
            background: var(--surface-soft);
        }

        .upload-zone input[type=file] {
            display: none;
        }

        .upload-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--primary-2);
        }

        .upload-meta {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .upload-hints {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .upload-hints span {
            border: 1px solid #d6e6e1;
            background: #fff;
            border-radius: 999px;
            padding: 3px 8px;
            font-size: 10px;
            color: #5e7069;
            font-weight: 600;
        }

        .upload-hints a {
            color: var(--primary-2);
            text-decoration: none;
            font-weight: 700;
        }

        .upload-hints a:hover {
            text-decoration: underline;
        }

        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
        }

        .module-grid-compact {
            gap: 8px;
        }

        .module-link {
            text-decoration: none;
            border: 1px solid #d6e6e1;
            border-radius: 12px;
            background: #fff;
            color: #56644a;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.45;
            transition: .18s ease;
        }

        .module-meta {
            margin: 6px 0 0;
        }

        .module-link:hover {
            border-color: #b8d4cc;
            background: var(--surface-soft);
            color: var(--primary-2);
            transform: translateY(-1px);
        }

        .avatar-preview {
            margin-top: 8px;
            width: 88px;
            height: 88px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid var(--line);
            background: #fff;
            display: block;
        }

        .avatar-xs {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--line);
            background: #fff;
            display: inline-block;
            vertical-align: middle;
        }

        .avatar-list {
            width: 62px;
            height: 62px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid var(--line);
            background: #fff;
            display: inline-block;
            vertical-align: middle;
            box-shadow: 0 8px 14px rgba(20, 48, 44, 0.12);
        }

        .inline-check {
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
            text-transform: none;
            letter-spacing: 0;
        }

        .inline-check input {
            width: auto;
            margin: 0;
        }

        .text-muted {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
        }

        .mini-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .mini-actions a {
            text-decoration: none;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 7px 11px;
            font-size: 11px;
            font-weight: 700;
            color: var(--primary-2);
            background: #fff;
            transition: .18s ease;
        }

        .mini-actions a:hover {
            border-color: #b8d4cc;
            background: var(--surface-soft);
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
            margin-bottom: 16px;
        }

        .metric-card {
            border-radius: 16px;
            padding: 18px 84px 18px 18px;
            color: #fff;
            box-shadow: var(--shadow-sm);
            min-height: 112px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .metric-card-icon {
            position: absolute;
            right: 14px;
            top: 14px;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.96);
            color: var(--primary);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.14);
        }

        .metric-card-icon svg {
            width: 28px;
            height: 28px;
            stroke-width: 2.15;
        }

        .metric-card-purple { background: linear-gradient(135deg, #1f8a83, #0f766e); }
        .metric-card-blue { background: linear-gradient(135deg, #60a5fa, #2563eb); }
        .metric-card-orange { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .metric-card-green { background: linear-gradient(135deg, #34d399, #059669); }

        .metric-number {
            margin: 0;
            font-size: 38px;
            font-weight: 800;
            line-height: 1;
        }

        .metric-label {
            margin: 8px 0 0;
            font-size: 14px;
            font-weight: 600;
            opacity: .94;
        }

        .dashboard-grid,
        .admin-grid-mid,
        .admin-grid-bottom {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        .chart-shell {
            padding: 14px;
        }

        .chart-legend {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 6px 0 0;
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 600;
        }

        .chart-legend span::before {
            content: "";
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .legend-present::before { background: var(--primary); }
        .legend-absent::before { background: var(--accent-blue); }
        .legend-leave::before { background: var(--accent-orange); }

        .attendance-bars {
            display: grid;
            gap: 10px;
            margin-top: 6px;
        }

        .bar-row {
            display: grid;
            grid-template-columns: 80px minmax(0, 1fr) 46px;
            align-items: center;
            gap: 8px;
        }

        .bar-row span {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .bar-track {
            height: 10px;
            border-radius: 999px;
            background: #edf7e8;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            border-radius: inherit;
        }

        .bar-present { background: linear-gradient(90deg, var(--primary), #74b816); }
        .bar-absent { background: linear-gradient(90deg, var(--accent-blue), #ffd43b); }
        .bar-leave { background: linear-gradient(90deg, var(--accent-orange), #ffcb67); }

        .quick-actions {
            margin-top: 12px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .quick-actions a {
            text-decoration: none;
            text-align: center;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 8px 9px;
            font-size: 11px;
            font-weight: 700;
            color: var(--primary-2);
            background: #fff;
        }

        .quick-actions a:hover {
            background: var(--surface-soft);
            border-color: #b8d4cc;
        }

        .donut-wrap {
            display: grid;
            grid-template-columns: 130px minmax(0, 1fr);
            gap: 12px;
            align-items: center;
            margin-top: 10px;
        }

        .donut-chart {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            position: relative;
            margin: 0 auto;
        }

        .donut-chart::after {
            content: "";
            position: absolute;
            inset: 23px;
            border-radius: 50%;
            background: #fff;
            border: 1px solid #ddeacb;
        }

        .top-list {
            display: grid;
            gap: 9px;
            margin-top: 8px;
        }

        .top-item {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: 9px;
            align-items: center;
            border: 1px solid #e2ecd2;
            border-radius: 12px;
            background: #fff;
            padding: 9px;
        }

        .rank-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent-orange), #ff8d3c);
        }

        .chart-canvas-wrap {
            position: relative;
            height: 240px;
            margin-top: 8px;
        }

        .dashboard-motion .workspace-main {
            position: relative;
            isolation: isolate;
        }

        .dashboard-motion .workspace-main::before,
        .dashboard-motion .workspace-main::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            filter: blur(2px);
            pointer-events: none;
            z-index: -1;
            opacity: .36;
            animation: dash-blob 14s ease-in-out infinite;
        }

        .dashboard-motion .workspace-main::before {
            width: 240px;
            height: 240px;
            top: -30px;
            right: 8%;
            background: radial-gradient(circle at 28% 28%, rgba(129, 230, 217, .48), rgba(43, 108, 176, 0));
        }

        .dashboard-motion .workspace-main::after {
            width: 280px;
            height: 280px;
            left: -80px;
            bottom: -80px;
            background: radial-gradient(circle at 35% 35%, rgba(250, 204, 21, .35), rgba(15, 118, 110, 0));
            animation-delay: -5s;
        }

        .dashboard-motion .title {
            background: linear-gradient(120deg, #093730 0%, #0f766e 34%, #2b6cb0 67%, #0b4c45 100%);
            background-size: 220% 100%;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: dash-fade-up .7s cubic-bezier(.2, .8, .2, 1) both, dash-title-wave 8s linear infinite;
            text-shadow: none;
        }

        .dashboard-motion .subtitle {
            animation: dash-fade-up .6s cubic-bezier(.2, .8, .2, 1) .12s both;
        }

        .dashboard-motion .topbar .mini-actions,
        .dashboard-motion .nav {
            animation: dash-fade-up .64s cubic-bezier(.2, .8, .2, 1) .18s both;
        }

        .dashboard-motion .is-reveal {
            opacity: 0;
            transform: translateY(18px) scale(.985);
            animation: dash-fade-card .62s cubic-bezier(.2, .8, .2, 1) both;
            animation-delay: calc(70ms + (var(--reveal-order, 0) * 65ms));
            will-change: transform, opacity;
        }

        .dashboard-motion .metric-card,
        .dashboard-motion .cards .card,
        .dashboard-motion .panel,
        .dashboard-motion .module-link,
        .dashboard-motion .workflow-link,
        .dashboard-motion .top-item {
            transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
        }

        .dashboard-motion .metric-card:hover,
        .dashboard-motion .cards .card:hover,
        .dashboard-motion .panel:hover,
        .dashboard-motion .module-link:hover,
        .dashboard-motion .workflow-link:hover,
        .dashboard-motion .top-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 28px rgba(21, 67, 60, .16);
        }

        .dashboard-motion .metric-number,
        .dashboard-motion .card .value {
            animation: dash-number-pulse 3.2s ease-in-out infinite;
        }

        .dashboard-motion .metric-card-icon,
        .dashboard-motion .card-icon {
            animation: dash-icon-pop .58s cubic-bezier(.19, 1, .22, 1) both;
            animation-delay: calc(140ms + (var(--reveal-order, 0) * 65ms));
        }

        .dashboard-motion .metric-card-icon svg,
        .dashboard-motion .card-icon svg {
            animation: dash-icon-float 3s ease-in-out infinite;
            transform-origin: center;
        }

        .dashboard-motion .sidebar-links .nav-icon svg {
            animation: dash-nav-icon 4.6s ease-in-out infinite;
            transform-origin: center;
        }

        .dashboard-motion .sidebar-links a:nth-child(2n) .nav-icon svg {
            animation-delay: .4s;
        }

        .dashboard-motion .sidebar-links a:nth-child(3n) .nav-icon svg {
            animation-delay: .9s;
        }

        .dashboard-motion .school-standard-pill i,
        .dashboard-motion .workflow-step {
            animation: dash-icon-float 3.4s ease-in-out infinite;
        }

        @keyframes dash-fade-up {
            from {
                opacity: 0;
                transform: translateY(14px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes dash-fade-card {
            from {
                opacity: 0;
                transform: translateY(18px) scale(.985);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes dash-title-wave {
            0% { background-position: 0% 50%; }
            100% { background-position: 200% 50%; }
        }

        @keyframes dash-icon-pop {
            from {
                opacity: 0;
                transform: scale(.7) rotate(-14deg);
            }

            to {
                opacity: 1;
                transform: scale(1) rotate(0deg);
            }
        }

        @keyframes dash-icon-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        @keyframes dash-nav-icon {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }

        @keyframes dash-number-pulse {
            0%, 100% { text-shadow: 0 0 0 rgba(15, 118, 110, 0); }
            50% { text-shadow: 0 6px 16px rgba(15, 118, 110, .16); }
        }

        @keyframes dash-blob {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50% { transform: translate3d(0, -16px, 0); }
        }

        .app-frame.sidebar-collapsed .workspace-shell {
            grid-template-columns: 90px minmax(0, 1fr);
        }

        .app-frame.sidebar-collapsed .global-sidebar {
            padding: 14px 8px;
        }

        .app-frame.sidebar-collapsed .sidebar-brand {
            justify-content: center;
            padding-inline: 0;
        }

        .app-frame.sidebar-collapsed .sidebar-brand > span:last-child,
        .app-frame.sidebar-collapsed .global-sidebar h3,
        .app-frame.sidebar-collapsed .sidebar-links a .nav-text {
            display: none;
        }

        .app-frame.sidebar-collapsed .sidebar-links a {
            justify-content: center;
            padding-inline: 0;
        }

        .app-frame.sidebar-collapsed .sidebar-links {
            margin-bottom: 8px;
        }

        .app-frame.sidebar-collapsed .nav-icon {
            width: 20px;
            height: 20px;
        }

        .rt-toast-stack {
            position: fixed;
            top: 18px;
            right: 18px;
            width: min(340px, calc(100vw - 36px));
            display: grid;
            gap: 8px;
            z-index: 9999;
        }

        .rt-toast {
            background: #2f3f24;
            color: #fff;
            border-radius: 12px;
            padding: 11px 12px;
            box-shadow: 0 14px 30px rgba(47, 63, 36, .33);
            border: 1px solid rgba(255, 255, 255, .16);
            animation: rt-slide-in .2s ease-out;
        }

        .rt-toast-title {
            font-size: 13px;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .rt-toast-content {
            font-size: 12px;
            opacity: .95;
            margin: 0;
        }

        .rt-toast-meta {
            font-size: 11px;
            opacity: .75;
            margin-top: 6px;
        }

        @keyframes rt-slide-in {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1180px) {
            .workspace-shell {
                grid-template-columns: 1fr;
            }

            .global-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                width: min(86vw, 340px);
                height: 100dvh;
                border-right: 1px solid var(--line);
                border-bottom: none;
                max-height: none;
                z-index: 60;
                transform: translateX(-104%);
                transition: transform .24s ease, padding .22s ease;
                box-shadow: 0 24px 44px rgba(13, 41, 37, 0.18);
            }

            .app-frame.sidebar-mobile-open .global-sidebar {
                transform: translateX(0);
            }

            .sidebar-backdrop {
                display: block;
                position: fixed;
                inset: 0;
                border: 0;
                padding: 0;
                background: rgba(16, 26, 24, 0.38);
                opacity: 0;
                pointer-events: none;
                z-index: 55;
                transition: opacity .2s ease;
            }

            .app-frame.sidebar-mobile-open .sidebar-backdrop {
                opacity: 1;
                pointer-events: auto;
            }

            .dashboard-grid,
            .admin-grid-mid,
            .admin-grid-bottom {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .shell {
                padding: 10px;
            }

            .workspace-main {
                padding: 12px;
            }

            .global-head {
                flex-direction: column;
                align-items: stretch;
                padding: 12px;
            }

            .head-left {
                width: 100%;
                flex-wrap: wrap;
            }

            .global-search {
                max-width: none;
                min-width: 100%;
            }

            .head-tools {
                justify-content: stretch;
                display: grid;
                grid-template-columns: 1fr;
            }

            .user-chip {
                max-width: none;
                width: 100%;
            }

            .role-pill,
            .head-link,
            .locale-inline-form,
            .logout {
                width: 100%;
                justify-content: center;
            }

            .locale-inline-form select {
                width: 100%;
            }

            .topbar {
                flex-direction: column;
                align-items: stretch;
            }

            .school-standard-strip {
                align-items: flex-start;
            }

            .school-standard-date {
                width: 100%;
                justify-content: flex-start;
            }

            .workflow-nav {
                grid-template-columns: 1fr;
            }

            .cards {
                grid-template-columns: 1fr;
            }

            .form-grid,
            .form-grid-wide,
            .module-grid {
                grid-template-columns: 1fr;
            }

            .upload-head,
            .school-standard-strip {
                flex-direction: column;
                align-items: stretch;
            }

            .mini-actions {
                justify-content: stretch;
            }

            .card {
                padding-right: 56px;
            }

            .card-icon {
                width: 34px;
                height: 34px;
            }

            .card-icon svg {
                width: 19px;
                height: 19px;
            }

            .avatar-list {
                width: 52px;
                height: 52px;
                border-radius: 10px;
            }

            .metric-card {
                padding-right: 68px;
            }

            .metric-card-icon {
                width: 40px;
                height: 40px;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .donut-wrap {
                grid-template-columns: 1fr;
                text-align: center;
                justify-items: center;
            }

            table {
                min-width: 560px;
                font-size: 13px;
            }

            th,
            td {
                padding: 10px 10px;
            }

            .panel-head {
                padding: 12px 14px;
            }

            .title {
                font-size: clamp(22px, 7vw, 30px);
            }

            .subtitle {
                font-size: 14px;
            }

            .nav {
                display: grid;
                grid-template-columns: 1fr;
            }

            .nav a,
            button {
                width: 100%;
            }

            form.inline-form {
                display: flex;
                margin: 8px 0 0;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .dashboard-motion .workspace-main::before,
            .dashboard-motion .workspace-main::after,
            .dashboard-motion .title,
            .dashboard-motion .subtitle,
            .dashboard-motion .topbar .mini-actions,
            .dashboard-motion .nav,
            .dashboard-motion .is-reveal,
            .dashboard-motion .metric-number,
            .dashboard-motion .card .value,
            .dashboard-motion .metric-card-icon,
            .dashboard-motion .card-icon,
            .dashboard-motion .metric-card-icon svg,
            .dashboard-motion .card-icon svg,
            .dashboard-motion .sidebar-links .nav-icon svg,
            .dashboard-motion .school-standard-pill i,
            .dashboard-motion .workflow-step {
                animation: none !important;
                transition: none !important;
                transform: none !important;
                opacity: 1 !important;
            }
        }
    </style>
</head>
@php
    $isDashboardRoute = request()->routeIs('dashboard')
        || request()->routeIs('admin.dashboard')
        || request()->routeIs('teacher.dashboard')
        || request()->routeIs('super-admin.dashboard')
        || request()->routeIs('student.dashboard')
        || request()->routeIs('parent.dashboard');
@endphp
<body class="locale-{{ app()->getLocale() }} {{ $isDashboardRoute ? 'dashboard-motion' : '' }}">
    <div class="shell">
        @auth
            @php
                $role = (string) (auth()->user()?->normalizedRole() ?? '');
                $roleLabel = match ($role) {
                    'super-admin' => __('ui.role.super_admin'),
                    'admin' => __('ui.role.admin'),
                    'teacher' => __('ui.role.teacher'),
                    'student' => __('ui.role.student'),
                    'parent', 'guardian' => __('ui.role.parent'),
                    default => ucfirst(str_replace('-', ' ', $role)),
                };
                $name = (string) (auth()->user()->name ?? 'User');
                $initial = strtoupper(mb_substr($name, 0, 1));
                $today = \Illuminate\Support\Carbon::now(config('app.timezone', 'Asia/Phnom_Penh'));
                $schoolDateLabel = app()->getLocale() === 'km'
                    ? $today->locale('km')->translatedFormat('l ទី d ខែ F ឆ្នាំ Y')
                    : $today->translatedFormat('l, d F Y');
            @endphp

            <div class="app-frame" id="app-frame">
                <div class="workspace-shell">
                    <aside class="global-sidebar" id="sidebar-nav">
                        <a class="sidebar-brand" href="{{ route('dashboard') }}">
                            <span class="brand-mark">
                                <img src="{{ asset('sala-digital-mark.svg') }}" alt="Sala Digital">
                            </span>
                            <span>
                                <p class="brand-name">Sala Digital</p>
                                <p class="brand-sub">{{ __('ui.layout.brand_sub') }}</p>
                            </span>
                        </a>

                        <h3>{{ __('ui.layout.home') }}</h3>
                        <div class="sidebar-links">
                            <a data-nav-item data-nav-icon="layout-dashboard" href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') || request()->routeIs('admin.dashboard') || request()->routeIs('teacher.dashboard') || request()->routeIs('super-admin.dashboard') || request()->routeIs('student.dashboard') || request()->routeIs('parent.dashboard') ? 'active' : '' }}">{{ __('ui.layout.dashboard') }}</a>
                        </div>

                        @if($role === 'super-admin')
                            <h3>{{ __('ui.layout.system') }}</h3>
                            <div class="sidebar-links">
                                <a data-nav-item data-nav-icon="school" href="{{ route('panel.schools.index') }}" class="{{ request()->routeIs('panel.schools.*') ? 'active' : '' }}">{{ __('ui.layout.school_directory') }}</a>
                                <a data-nav-item data-nav-icon="users" href="{{ route('panel.users.index', ['role' => 'admin']) }}" class="{{ request()->fullUrlIs('*role=admin*') ? 'active' : '' }}">{{ __('ui.layout.admin_directory') }}</a>
                                <a data-nav-item data-nav-icon="panel-top" href="{{ route('super-admin.dashboard') }}" class="{{ request()->routeIs('super-admin.dashboard') ? 'active' : '' }}">{{ __('ui.layout.system_overview') }}</a>
                            </div>
                        @endif

                        @if($role !== 'super-admin')
                            @can('web-manage-students')
                                <h3>{{ __('ui.layout.students') }}</h3>
                                <div class="sidebar-links">
                                    <a data-nav-item data-nav-icon="graduation-cap" href="{{ route('panel.students.index') }}" class="{{ request()->routeIs('panel.students.index') || request()->routeIs('panel.students.show') ? 'active' : '' }}">{{ __('ui.layout.student_list') }}</a>
                                    <a data-nav-item data-nav-icon="user-round-plus" href="{{ route('panel.students.create') }}" class="{{ request()->routeIs('panel.students.create') ? 'active' : '' }}">{{ __('ui.layout.create_student') }}</a>
                                </div>
                            @endcan
                        @endif

                        @can('web-manage-users')
                            <h3>{{ __('ui.layout.teachers_guardians') }}</h3>
                            <div class="sidebar-links">
                                <a data-nav-item data-nav-icon="presentation" href="{{ route('panel.users.index', ['role' => 'teacher']) }}" class="{{ request()->fullUrlIs('*role=teacher*') ? 'active' : '' }}">{{ __('ui.layout.teacher_list') }}</a>
                                <a data-nav-item data-nav-icon="user-round-plus" href="{{ route('panel.users.create', ['role' => 'teacher']) }}" class="{{ request()->routeIs('panel.users.create') && request('role') === 'teacher' ? 'active' : '' }}">{{ __('ui.layout.create_teacher') }}</a>
                                <a data-nav-item data-nav-icon="users-round" href="{{ route('panel.users.index', ['role' => 'parent']) }}" class="{{ request()->fullUrlIs('*role=parent*') ? 'active' : '' }}">{{ __('ui.layout.guardian_list') }}</a>
                                <a data-nav-item data-nav-icon="user-plus" href="{{ route('panel.users.create', ['role' => 'parent']) }}" class="{{ request()->routeIs('panel.users.create') && request('role') === 'parent' ? 'active' : '' }}">{{ __('ui.layout.create_parent') }}</a>
                            </div>
                        @endcan

                        @can('web-manage-classes')
                            <h3>{{ __('ui.layout.classes') }}</h3>
                            <div class="sidebar-links">
                                <a data-nav-item data-nav-icon="school" href="{{ route('panel.classes.index') }}" class="{{ request()->routeIs('panel.classes.index') || request()->routeIs('panel.classes.show') || request()->routeIs('panel.classes.edit') ? 'active' : '' }}">{{ __('ui.layout.class_list') }}</a>
                                <a data-nav-item data-nav-icon="plus-circle" href="{{ route('panel.classes.create') }}" class="{{ request()->routeIs('panel.classes.create') ? 'active' : '' }}">{{ __('ui.layout.create_class') }}</a>
                            </div>
                        @endcan

                        @can('web-manage-subjects')
                            <h3>{{ __('ui.layout.subjects') }}</h3>
                            <div class="sidebar-links">
                                <a data-nav-item data-nav-icon="book-open" href="{{ route('panel.subjects.index') }}" class="{{ request()->routeIs('panel.subjects.index') || request()->routeIs('panel.subjects.edit') ? 'active' : '' }}">{{ __('ui.layout.subject_list') }}</a>
                                <a data-nav-item data-nav-icon="plus-circle" href="{{ route('panel.subjects.create') }}" class="{{ request()->routeIs('panel.subjects.create') ? 'active' : '' }}">{{ __('ui.layout.create_subject') }}</a>
                            </div>
                        @endcan

                        @can('web-manage-timetables')
                            <h3>{{ __('ui.layout.timetable') }}</h3>
                            <div class="sidebar-links">
                                <a data-nav-item data-nav-icon="calendar-clock" href="{{ route('panel.timetables.index') }}" class="{{ request()->routeIs('panel.timetables.*') ? 'active' : '' }}">{{ __('ui.layout.routine') }}</a>
                            </div>
                        @endcan

                        @can('web-manage-attendance')
                            <h3>{{ __('ui.layout.attendance') }}</h3>
                            <div class="sidebar-links">
                                <a data-nav-item data-nav-icon="clipboard-check" href="{{ route('panel.attendance.index') }}" class="{{ request()->routeIs('panel.attendance.index') ? 'active' : '' }}">{{ __('ui.layout.attendance_list') }}</a>
                                <a data-nav-item data-nav-icon="check-circle-2" href="{{ route('panel.attendance.create') }}" class="{{ request()->routeIs('panel.attendance.create') ? 'active' : '' }}">{{ __('ui.layout.check_attendance') }}</a>
                            </div>
                        @endcan

                        @can('web-manage-scores')
                            <h3>{{ __('ui.layout.exams_scores') }}</h3>
                            <div class="sidebar-links">
                                <a data-nav-item data-nav-icon="badge-percent" href="{{ route('panel.scores.index') }}" class="{{ request()->routeIs('panel.scores.*') ? 'active' : '' }}">{{ __('ui.layout.scores') }}</a>
                            </div>
                        @endcan

                        @if($role === 'teacher' && Gate::allows('web-manage-homeworks'))
                            <h3>{{ __('ui.layout.homework') }}</h3>
                            <div class="sidebar-links">
                                <a data-nav-item data-nav-icon="file-text" href="{{ route('panel.homeworks.index') }}" class="{{ request()->routeIs('panel.homeworks.*') ? 'active' : '' }}">{{ __('ui.layout.homework') }}</a>
                            </div>
                        @endif

                        @if(in_array($role, ['super-admin', 'admin', 'teacher'], true))
                            <h3>{{ __('ui.layout.media') }}</h3>
                            <div class="sidebar-links">
                                <a data-nav-item data-nav-icon="image" href="{{ route('panel.media.index') }}" class="{{ request()->routeIs('panel.media.*') ? 'active' : '' }}">{{ __('ui.layout.media_library') }}</a>
                            </div>
                        @endif

                        @can('web-view-announcements')
                            <h3>{{ __('ui.layout.notice') }}</h3>
                            <div class="sidebar-links">
                                <a data-nav-item data-nav-icon="megaphone" href="{{ route('panel.announcements.index') }}" class="{{ request()->routeIs('panel.announcements.index') || request()->routeIs('panel.announcements.edit') ? 'active' : '' }}">{{ __('ui.layout.announcements') }}</a>
                                @can('web-manage-announcements')
                                    <a data-nav-item data-nav-icon="plus-circle" href="{{ route('panel.announcements.create') }}" class="{{ request()->routeIs('panel.announcements.create') ? 'active' : '' }}">{{ __('ui.layout.create_announcement') }}</a>
                                @endcan
                            </div>
                        @endcan

                        @can('web-manage-notifications')
                            <h3>{{ __('ui.layout.notifications') }}</h3>
                            <div class="sidebar-links">
                                <a data-nav-item data-nav-icon="bell-ring" href="{{ route('panel.notifications.index') }}" class="{{ request()->routeIs('panel.notifications.*') ? 'active' : '' }}">{{ __('ui.layout.send_notifications') }}</a>
                            </div>
                        @endcan

                        @can('web-view-messages')
                            <h3>{{ __('ui.layout.messages') }}</h3>
                            <div class="sidebar-links">
                                <a data-nav-item data-nav-icon="messages-square" href="{{ route('panel.messages.index') }}" class="{{ request()->routeIs('panel.messages.index') || request()->routeIs('panel.messages.show') ? 'active' : '' }}">{{ __('ui.layout.message_center') }}</a>
                                @can('web-create-messages')
                                    <a data-nav-item data-nav-icon="plus-circle" href="{{ route('panel.messages.create') }}" class="{{ request()->routeIs('panel.messages.create') ? 'active' : '' }}">{{ __('ui.layout.create_message') }}</a>
                                @endcan
                            </div>
                        @endcan

                        @can('web-view-leave-requests')
                            <h3>{{ __('ui.layout.leave') }}</h3>
                            <div class="sidebar-links">
                                <a data-nav-item data-nav-icon="calendar-minus" href="{{ route('panel.leave-requests.index') }}" class="{{ request()->routeIs('panel.leave-requests.*') ? 'active' : '' }}">{{ __('ui.layout.leave_requests') }}</a>
                            </div>
                        @endcan

                        @can('web-manage-incident-reports')
                            <h3>{{ __('ui.layout.incidents') }}</h3>
                            <div class="sidebar-links">
                                <a data-nav-item data-nav-icon="triangle-alert" href="{{ route('panel.incident-reports.index') }}" class="{{ request()->routeIs('panel.incident-reports.index') || request()->routeIs('panel.incident-reports.edit') ? 'active' : '' }}">{{ __('ui.layout.incident_reports') }}</a>
                                <a data-nav-item data-nav-icon="plus-circle" href="{{ route('panel.incident-reports.create') }}" class="{{ request()->routeIs('panel.incident-reports.create') ? 'active' : '' }}">{{ __('ui.layout.create_incident_report') }}</a>
                            </div>
                        @endcan

                        @can('web-view-audit-logs')
                            <h3>{{ __('ui.layout.audit') }}</h3>
                            <div class="sidebar-links">
                                <a data-nav-item data-nav-icon="shield-check" href="{{ route('panel.audit-logs.index') }}" class="{{ request()->routeIs('panel.audit-logs.*') ? 'active' : '' }}">{{ __('ui.layout.audit_logs') }}</a>
                            </div>
                        @endcan

                        <h3>{{ __('ui.layout.account') }}</h3>
                        <div class="sidebar-links">
                            <a data-nav-item data-nav-icon="user-circle" href="{{ route('profile.show') }}" class="{{ request()->routeIs('profile.*') ? 'active' : '' }}">{{ __('ui.layout.my_profile') }}</a>
                        </div>
                    </aside>
                    <button type="button" class="sidebar-backdrop" id="sidebar-backdrop" aria-label="Close menu"></button>

                    <main class="workspace-main">
                        <header class="global-head">
                            <div class="head-left">
                                <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle sidebar" aria-pressed="false">
                                    <i data-lucide="panel-left-close"></i>
                                </button>
                                <div class="global-search">
                                    <input id="sidebar-filter-input" type="text" placeholder="{{ __('ui.layout.search_placeholder') }}">
                                </div>
                            </div>

                            <div class="head-tools">
                                <form method="POST" action="{{ route('locale.switch') }}" class="locale-inline-form">
                                    @csrf
                                    <select name="locale" onchange="this.form.submit()">
                                        <option value="en" {{ app()->getLocale() === 'en' ? 'selected' : '' }}>{{ __('ui.locale.english') }}</option>
                                        <option value="km" {{ app()->getLocale() === 'km' ? 'selected' : '' }}>{{ __('ui.locale.khmer') }}</option>
                                    </select>
                                </form>
                                <span class="role-pill">{{ $roleLabel }}</span>
                                <span class="user-chip">
                                    <span class="user-avatar">{{ $initial !== '' ? $initial : 'U' }}</span>
                                    <span>
                                        <span class="name">{{ auth()->user()->name }}</span>
                                        <span class="email">{{ auth()->user()->email }}</span>
                                    </span>
                                </span>
                                <a class="head-link" href="{{ route('dashboard') }}">{{ __('ui.layout.dashboard') }}</a>
                                <a class="head-link" href="{{ route('profile.show') }}">{{ __('ui.layout.profile') }}</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="logout" type="submit">{{ __('ui.layout.sign_out') }}</button>
                                </form>
                            </div>
                        </header>

                        <div class="school-standard-strip">
                            <div class="school-standard-meta">
                                @if(in_array($role, ['super-admin', 'admin'], true))
                                    <a class="school-standard-pill school-standard-pill-link" href="{{ route('panel.classes.index') }}">
                                        <i data-lucide="school"></i>
                                        {{ __('ui.layout.khmer_school_standard') }}
                                    </a>
                                @else
                                    <span class="school-standard-pill">
                                        <i data-lucide="school"></i>
                                        {{ __('ui.layout.khmer_school_standard') }}
                                    </span>
                                @endif
                                <span class="school-standard-pill">
                                    <i data-lucide="book-open"></i>
                                    {{ __('ui.layout.learning_quality') }}
                                </span>
                            </div>
                            <span class="school-standard-date">
                                <i data-lucide="calendar-days"></i>
                                {{ $schoolDateLabel }}
                            </span>
                        </div>

                        @if(in_array($role, ['super-admin', 'admin'], true))
                            @php
                                $workflowItems = [
                                    [
                                        'title' => __('ui.layout.workflow_create'),
                                        'desc' => __('ui.layout.workflow_create_desc'),
                                        'url' => $role === 'super-admin'
                                            ? route('panel.schools.create')
                                            : route('panel.users.create'),
                                        'active' => $role === 'super-admin'
                                            ? (request()->routeIs('panel.schools.*') || request()->routeIs('panel.users.create'))
                                            : request()->routeIs('panel.users.create'),
                                    ],
                                    [
                                        'title' => __('ui.layout.workflow_structure'),
                                        'desc' => __('ui.layout.workflow_structure_desc'),
                                        'url' => route('panel.classes.index'),
                                        'active' => request()->routeIs('panel.classes.*') || request()->routeIs('panel.subjects.*'),
                                    ],
                                    [
                                        'title' => __('ui.layout.workflow_school_setup'),
                                        'desc' => __('ui.layout.workflow_school_setup_desc'),
                                        'url' => route('panel.timetables.index'),
                                        'active' => request()->routeIs('panel.timetables.*')
                                            || request()->routeIs('panel.attendance.*')
                                            || request()->routeIs('panel.scores.*')
                                            || request()->routeIs('panel.announcements.*')
                                            || request()->routeIs('panel.notifications.*')
                                            || request()->routeIs('panel.messages.*')
                                            || request()->routeIs('panel.leave-requests.*')
                                            || request()->routeIs('panel.incident-reports.*'),
                                    ],
                                    [
                                        'title' => __('ui.layout.workflow_users'),
                                        'desc' => __('ui.layout.workflow_users_desc'),
                                        'url' => $role === 'super-admin'
                                            ? route('panel.users.index', ['role' => 'admin'])
                                            : route('panel.users.index'),
                                        'active' => request()->routeIs('panel.users.index')
                                            || request()->routeIs('panel.users.edit')
                                            || request()->routeIs('panel.users.show'),
                                    ],
                                ];
                            @endphp
                            <nav class="workflow-nav" aria-label="Setup workflow">
                                @foreach($workflowItems as $item)
                                    <a href="{{ $item['url'] }}" class="workflow-link {{ $item['active'] ? 'active' : '' }}">
                                        <span class="workflow-step">{{ $loop->iteration }}</span>
                                        <span class="workflow-title">{{ $item['title'] }}</span>
                                        <span class="workflow-desc">{{ $item['desc'] }}</span>
                                    </a>
                                @endforeach
                            </nav>
                        @endif

                        @yield('content')
                    </main>
                </div>
            </div>
        @else
            @yield('content')
        @endauth
    </div>

    <div id="rt-toast-stack" class="rt-toast-stack"></div>
    <script src="https://unpkg.com/lucide@0.462.0/dist/umd/lucide.min.js"></script>
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var navSearchInput = document.getElementById('sidebar-filter-input');
            var sidebar = document.getElementById('sidebar-nav');
            var navItems = [];
            var normalize = function (value) {
                return (value || '')
                    .toString()
                    .trim()
                    .toLowerCase()
                    .replace(/\s+/g, ' ');
            };

            if (sidebar) {
                navItems = Array.prototype.slice.call(sidebar.querySelectorAll('[data-nav-item]'));

                var iconByLabel = {
                    'dashboard': 'layout-dashboard',
                    'student list': 'graduation-cap',
                    'create student': 'user-round-plus',
                    'teacher list': 'presentation',
                    'guardian list': 'users-round',
                    'create teacher/guardian': 'user-plus',
                    'class & room': 'school',
                    'subject list': 'book-open',
                    'routine': 'calendar-clock',
                    'attendance list': 'clipboard-check',
                    'check attendance': 'check-circle-2',
                    'scores': 'badge-percent',
                    'homework': 'file-text',
                    'media library': 'image',
                    'announcements': 'megaphone',
                    'send notifications': 'bell-ring',
                    'message center': 'messages-square',
                    'leave requests': 'calendar-minus',
                    'incident reports': 'triangle-alert',
                    'audit logs': 'shield-check',
                    'my profile': 'user-circle'
                };

                navItems.forEach(function (item) {
                    if (item.getAttribute('data-nav-enhanced') === '1') {
                        return;
                    }

                    var label = normalize(item.textContent);
                    var icon = item.getAttribute('data-nav-icon') || iconByLabel[label] || 'dot';
                    item.classList.add('nav-item-link');
                    item.innerHTML = '<span class="nav-icon"><i data-lucide=\"' + icon + '\"></i></span><span class=\"nav-text\">' + item.textContent.trim() + '</span>';
                    item.setAttribute('data-nav-enhanced', '1');
                });
            }

            var statIconByLabel = {
                'students': 'graduation-cap',
                'student': 'graduation-cap',
                'teachers': 'presentation',
                'teacher': 'presentation',
                'parents': 'users-round',
                'guardians': 'users-round',
                'earnings': 'badge-dollar-sign',
                'admins': 'shield-check',
                'classes': 'school',
                'subjects': 'book-open',
                'homeworks': 'file-text',
                'present': 'check-circle-2',
                'present (p)': 'check-circle-2',
                'absent': 'x-circle',
                'absent (a)': 'x-circle',
                'leave': 'calendar-minus',
                'leave (l)': 'calendar-minus',
                'gpa': 'badge-percent',
                'avg score': 'chart-column',
                'incident count': 'triangle-alert',
                'incidents acknowledged': 'shield-check',
                'incidents unacknowledged': 'bell-ring',
                'assigned classes': 'school',
                'pending leaves': 'calendar-clock',
                'total users': 'users',
                'total classes': 'school',
                'total schools': 'school',
                'total subjects': 'book-open',
                'total students': 'graduation-cap'
            };

            var fallbackIcons = ['activity', 'chart-column', 'badge-percent', 'sparkles'];
            var resolveDashboardIcon = function (label, index) {
                var normalized = normalize(label);
                if (statIconByLabel[normalized]) {
                    return statIconByLabel[normalized];
                }

                if (normalized.indexOf('student') !== -1) return 'graduation-cap';
                if (normalized.indexOf('teacher') !== -1) return 'presentation';
                if (normalized.indexOf('parent') !== -1 || normalized.indexOf('guardian') !== -1) return 'users-round';
                if (normalized.indexOf('class') !== -1) return 'school';
                if (normalized.indexOf('score') !== -1 || normalized.indexOf('gpa') !== -1 || normalized.indexOf('exam') !== -1) return 'badge-percent';
                if (normalized.indexOf('attendance') !== -1 || normalized.indexOf('present') !== -1) return 'check-circle-2';
                if (normalized.indexOf('leave') !== -1) return 'calendar-minus';
                if (normalized.indexOf('incident') !== -1) return 'triangle-alert';
                if (normalized.indexOf('subject') !== -1) return 'book-open';
                if (normalized.indexOf('homework') !== -1) return 'file-text';
                if (normalized.indexOf('សិស្ស') !== -1) return 'graduation-cap';
                if (normalized.indexOf('គ្រូ') !== -1) return 'presentation';
                if (normalized.indexOf('អាណាព្យាបាល') !== -1) return 'users-round';
                if (normalized.indexOf('ថ្នាក់') !== -1) return 'school';
                if (normalized.indexOf('មុខវិជ្ជា') !== -1) return 'book-open';
                if (normalized.indexOf('វត្តមាន') !== -1 || normalized.indexOf('ចូល') !== -1) return 'check-circle-2';
                if (normalized.indexOf('សុំច្បាប់') !== -1 || normalized.indexOf('ច្បាប់') !== -1) return 'calendar-minus';
                if (normalized.indexOf('ពិន្ទុ') !== -1 || normalized.indexOf('មធ្យមភាគ') !== -1) return 'badge-percent';
                if (normalized.indexOf('កិច្ចការ') !== -1) return 'file-text';
                if (normalized.indexOf('ហេតុការណ៍') !== -1) return 'triangle-alert';
                if (normalized.indexOf('អ្នកប្រើ') !== -1) return 'users';

                return fallbackIcons[index % fallbackIcons.length];
            };

            var metricCards = document.querySelectorAll('.metric-card');
            metricCards.forEach(function (card, index) {
                if (card.querySelector('.metric-card-icon')) {
                    return;
                }

                var labelNode = card.querySelector('.metric-label');
                var label = labelNode ? labelNode.textContent : '';
                var icon = resolveDashboardIcon(label, index);
                var iconWrap = document.createElement('span');
                iconWrap.className = 'metric-card-icon';
                iconWrap.innerHTML = '<i data-lucide=\"' + icon + '\"></i>';
                card.appendChild(iconWrap);
            });

            var statCards = document.querySelectorAll('.cards .card');
            statCards.forEach(function (card, index) {
                if (card.querySelector('.card-icon')) {
                    return;
                }

                var labelNode = card.querySelector('.label');
                var label = labelNode ? labelNode.textContent : '';
                var icon = resolveDashboardIcon(label, index);
                var iconWrap = document.createElement('span');
                iconWrap.className = 'card-icon';
                iconWrap.innerHTML = '<i data-lucide=\"' + icon + '\"></i>';
                card.appendChild(iconWrap);
            });

            if (document.body.classList.contains('dashboard-motion')) {
                var revealTargets = document.querySelectorAll(
                    '.workflow-link, .metric-card, .cards > .card, .panel, .module-link, .top-item, .nav a'
                );

                Array.prototype.forEach.call(revealTargets, function (element, index) {
                    if (element.classList.contains('is-reveal')) {
                        return;
                    }

                    element.classList.add('is-reveal');
                    element.style.setProperty('--reveal-order', String(Math.min(index, 18)));
                });
            }

            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }

            var appFrame = document.getElementById('app-frame');
            var sidebarToggle = document.getElementById('sidebar-toggle');
            var sidebarBackdrop = document.getElementById('sidebar-backdrop');
            var sidebarStorageKey = 'school_ui_sidebar_collapsed_v1';
            var mobileBreakpoint = 1180;

            var setCollapsedState = function (collapsed) {
                if (!appFrame) {
                    return;
                }

                appFrame.classList.toggle('sidebar-collapsed', collapsed);
                if (sidebarToggle) {
                    sidebarToggle.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
                    var iconNode = sidebarToggle.querySelector('i');
                    if (iconNode) {
                        iconNode.setAttribute('data-lucide', collapsed ? 'panel-left-open' : 'panel-left-close');
                    }
                }
                if (window.lucide && typeof window.lucide.createIcons === 'function') {
                    window.lucide.createIcons();
                }
            };

            var setMobileSidebarState = function (open) {
                if (!appFrame) {
                    return;
                }

                appFrame.classList.toggle('sidebar-mobile-open', open);
                document.body.classList.toggle('sidebar-mobile-lock', open);
                if (sidebarToggle) {
                    sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                }
            };

            var closeMobileSidebar = function () {
                setMobileSidebarState(false);
            };

            if (sidebarToggle && appFrame && window.innerWidth > mobileBreakpoint) {
                var storedCollapsed = false;
                try {
                    storedCollapsed = window.localStorage.getItem(sidebarStorageKey) === '1';
                } catch (error) {
                    storedCollapsed = false;
                }

                setCollapsedState(storedCollapsed);
                sidebarToggle.addEventListener('click', function () {
                    var next = !appFrame.classList.contains('sidebar-collapsed');
                    setCollapsedState(next);
                    try {
                        window.localStorage.setItem(sidebarStorageKey, next ? '1' : '0');
                    } catch (error) {
                        // Ignore localStorage failures.
                    }
                });
            } else {
                setCollapsedState(false);
                if (sidebarToggle && appFrame) {
                    sidebarToggle.addEventListener('click', function () {
                        setMobileSidebarState(!appFrame.classList.contains('sidebar-mobile-open'));
                    });
                }
            }

            if (sidebarBackdrop) {
                sidebarBackdrop.addEventListener('click', closeMobileSidebar);
            }

            if (navItems.length > 0) {
                navItems.forEach(function (item) {
                    item.addEventListener('click', function () {
                        if (window.innerWidth <= mobileBreakpoint) {
                            closeMobileSidebar();
                        }
                    });
                });
            }

            window.addEventListener('resize', function () {
                if (window.innerWidth > mobileBreakpoint) {
                    closeMobileSidebar();
                }
            });

            if (navSearchInput && sidebar && navItems.length > 0) {
                navSearchInput.addEventListener('input', function () {
                    var query = navSearchInput.value.trim().toLowerCase();
                    navItems.forEach(function (item) {
                        var textNode = item.querySelector('.nav-text');
                        var text = (textNode ? textNode.textContent : item.textContent || '').toLowerCase();
                        item.style.display = query === '' || text.indexOf(query) !== -1 ? '' : 'none';
                    });
                });
            }

            var searchInputs = document.querySelectorAll('[data-select-search-for]');

            searchInputs.forEach(function (input) {
                var selectId = input.getAttribute('data-select-search-for');
                if (!selectId) {
                    return;
                }

                var select = document.getElementById(selectId);
                if (!select) {
                    return;
                }

                var applyFilter = function () {
                    var query = input.value.trim().toLowerCase();
                    var normalizedQuery = query.replace(/[^a-z0-9]+/g, ' ').trim();
                    var selectedValue = select.value;
                    var options = Array.prototype.slice.call(select.options);
                    var hasVisibleMatch = false;

                    options.forEach(function (option) {
                        if (option.value === '') {
                            option.hidden = false;
                            return;
                        }

                        if (query === '') {
                            option.hidden = false;
                            hasVisibleMatch = true;
                            return;
                        }

                        var optionText = option.text.toLowerCase();
                        var normalizedText = optionText.replace(/[^a-z0-9]+/g, ' ').trim();
                        var matches = optionText.indexOf(query) !== -1 || (normalizedQuery !== '' && normalizedText.indexOf(normalizedQuery) !== -1);
                        var keepSelectedVisible = option.value === selectedValue;
                        option.hidden = !matches && !keepSelectedVisible;
                        if (!option.hidden) {
                            hasVisibleMatch = true;
                        }
                    });

                    if (query !== '' && !hasVisibleMatch) {
                        options.forEach(function (option) {
                            option.hidden = false;
                        });
                    }
                };

                input.addEventListener('input', applyFilter);
                applyFilter();
            });

            var uploadZones = document.querySelectorAll('[data-upload-zone]');
            uploadZones.forEach(function (zone) {
                var fileInput = zone.querySelector('input[type="file"]');
                var meta = zone.querySelector('[data-upload-meta]');
                if (!fileInput) {
                    return;
                }

                var setMeta = function () {
                    if (!meta) {
                        return;
                    }

                    if (!fileInput.files || !fileInput.files.length) {
                        meta.textContent = 'No file selected';
                        return;
                    }

                    var file = fileInput.files[0];
                    var kb = Math.max(1, Math.round(file.size / 1024));
                    meta.textContent = file.name + ' (' + kb + ' KB)';
                };

                fileInput.addEventListener('change', setMeta);
                setMeta();

                ['dragenter', 'dragover'].forEach(function (eventName) {
                    zone.addEventListener(eventName, function (event) {
                        event.preventDefault();
                        zone.classList.add('dragover');
                    });
                });

                ['dragleave', 'drop'].forEach(function (eventName) {
                    zone.addEventListener(eventName, function (event) {
                        event.preventDefault();
                        zone.classList.remove('dragover');
                    });
                });

                zone.addEventListener('drop', function (event) {
                    var files = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files : null;
                    if (!files || !files.length) {
                        return;
                    }

                    try {
                        var dataTransfer = new DataTransfer();
                        Array.prototype.forEach.call(files, function (file) {
                            dataTransfer.items.add(file);
                        });
                        fileInput.files = dataTransfer.files;
                    } catch (e) {
                        return;
                    }
                    setMeta();
                });
            });

            var imageFileInputs = document.querySelectorAll('input[type="file"]');
            imageFileInputs.forEach(function (fileInput) {
                if (fileInput.getAttribute('data-image-preview-bound') === '1') {
                    return;
                }

                var accept = (fileInput.getAttribute('accept') || '').toLowerCase();
                var isImageInput = fileInput.name === 'image' || accept.indexOf('image') !== -1 || accept.indexOf('.jpg') !== -1 || accept.indexOf('.png') !== -1;
                if (!isImageInput) {
                    return;
                }

                fileInput.setAttribute('data-image-preview-bound', '1');
                var container = fileInput.parentElement || document;
                var preview = container.querySelector('img.avatar-preview');
                var removeToggle = container.querySelector('input[name="remove_image"]');
                var originalSrc = preview && preview.getAttribute('src') ? preview.getAttribute('src') : '';
                var localUrl = '';

                if (!preview) {
                    preview = document.createElement('img');
                    preview.className = 'avatar-preview';
                    preview.alt = 'Selected image preview';
                    preview.style.display = 'none';
                    preview.style.marginTop = '8px';

                    var insertAfter = fileInput.nextElementSibling;
                    if (insertAfter) {
                        insertAfter.insertAdjacentElement('afterend', preview);
                    } else {
                        fileInput.insertAdjacentElement('afterend', preview);
                    }
                }

                var revokeLocalUrl = function () {
                    if (localUrl !== '') {
                        try {
                            URL.revokeObjectURL(localUrl);
                        } catch (error) {
                            // Ignore URL revoke errors.
                        }
                        localUrl = '';
                    }
                };

                fileInput.addEventListener('change', function () {
                    var file = fileInput.files && fileInput.files.length ? fileInput.files[0] : null;
                    if (!file) {
                        revokeLocalUrl();
                        if (originalSrc !== '') {
                            preview.src = originalSrc;
                            preview.style.display = '';
                            preview.style.opacity = removeToggle && removeToggle.checked ? '.45' : '1';
                        } else {
                            preview.removeAttribute('src');
                            preview.style.display = 'none';
                        }
                        return;
                    }

                    if (file.type && file.type.indexOf('image/') !== 0) {
                        return;
                    }

                    revokeLocalUrl();
                    localUrl = URL.createObjectURL(file);
                    preview.src = localUrl;
                    preview.style.display = '';
                    preview.style.opacity = '1';

                    if (removeToggle) {
                        removeToggle.checked = false;
                    }
                });

                if (removeToggle) {
                    removeToggle.addEventListener('change', function () {
                        preview.style.opacity = removeToggle.checked ? '.45' : '1';
                    });
                }
            });

            var toastStack = document.getElementById('rt-toast-stack');
            var showToast = function (payload) {
                if (!toastStack || !payload || typeof payload !== 'object') {
                    return;
                }

                var title = (payload.title || 'New update').toString();
                var content = (payload.content || '').toString();
                var type = (payload.type || 'notification').toString();

                var toast = document.createElement('div');
                toast.className = 'rt-toast';

                var titleNode = document.createElement('p');
                titleNode.className = 'rt-toast-title';
                titleNode.textContent = title;

                var contentNode = document.createElement('p');
                contentNode.className = 'rt-toast-content';
                contentNode.textContent = content;

                var metaNode = document.createElement('div');
                metaNode.className = 'rt-toast-meta';
                metaNode.textContent = type;

                toast.appendChild(titleNode);
                toast.appendChild(contentNode);
                toast.appendChild(metaNode);

                toastStack.prepend(toast);

                window.setTimeout(function () {
                    toast.remove();
                }, 6500);
            };

            var currentUserId = Number(@json(optional(auth()->user())->id));
            var broadcastKey = @json(config('broadcasting.connections.pusher.key'));
            var broadcastHost = @json(config('broadcasting.connections.pusher.options.host'));
            var broadcastPort = Number(@json(config('broadcasting.connections.pusher.options.port')));
            var broadcastScheme = @json(config('broadcasting.connections.pusher.options.scheme'));
            var broadcastCluster = @json(config('broadcasting.connections.pusher.options.cluster') ?? env('PUSHER_APP_CLUSTER'));

            if (!currentUserId || !broadcastKey || !window.Pusher || !window.Echo) {
                return;
            }

            var EchoConstructor = window.Echo;
            if (typeof EchoConstructor !== 'function') {
                return;
            }

            var echo = new EchoConstructor({
                broadcaster: 'pusher',
                key: broadcastKey,
                cluster: broadcastCluster || 'mt1',
                forceTLS: broadcastScheme === 'https',
                authEndpoint: '/broadcasting/auth',
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': @json(csrf_token())
                    }
                }
            });

            if (broadcastHost) {
                echo = new EchoConstructor({
                    broadcaster: 'pusher',
                    key: broadcastKey,
                    cluster: broadcastCluster || 'mt1',
                    wsHost: broadcastHost,
                    wsPort: broadcastPort || 6001,
                    wssPort: broadcastPort || 6001,
                    forceTLS: broadcastScheme === 'https',
                    enabledTransports: ['ws', 'wss'],
                    authEndpoint: '/broadcasting/auth',
                    auth: {
                        headers: {
                            'X-CSRF-TOKEN': @json(csrf_token())
                        }
                    }
                });
            }

            window.schoolEcho = echo;
            echo.private('users.' + currentUserId)
                .listen('.realtime.notification', function (eventPayload) {
                    showToast(eventPayload);
                });
        });
    </script>
    @stack('scripts')
</body>
</html>
