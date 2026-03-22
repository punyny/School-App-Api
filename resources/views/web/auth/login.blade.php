<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | {{ config('app.name', 'Sala Digital') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('sala-digital-mark.svg') }}">
    <link rel="shortcut icon" href="{{ asset('sala-digital-mark.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Kantumruy+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-1: #f6f1e6;
            --bg-2: #e9f2f4;
            --surface: rgba(255, 255, 255, 0.92);
            --surface-soft: rgba(255, 255, 255, 0.72);
            --line: rgba(15, 118, 110, 0.14);
            --text-main: #182620;
            --text-muted: #5b6b65;
            --primary: #0f766e;
            --primary-2: #0b4c45;
            --accent-blue: #2563eb;
            --accent-orange: #f97316;
            --danger: #be123c;
            --success: #15803d;
            --shadow-lg: 0 30px 60px rgba(21, 46, 42, 0.16);
            --shadow-sm: 0 14px 30px rgba(15, 23, 42, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Sora", "Kantumruy Pro", sans-serif;
            color: var(--text-main);
            background:
                radial-gradient(880px 420px at -10% -12%, #dbeafe 0%, transparent 70%),
                radial-gradient(860px 420px at 110% 110%, #fde68a 0%, transparent 68%),
                linear-gradient(140deg, var(--bg-1) 0%, #fff3e5 40%, var(--bg-2) 100%);
        }

        body.locale-km {
            font-family: "Kantumruy Pro", "Sora", sans-serif;
        }

        .page-shell {
            max-width: 1220px;
            margin: 0 auto;
            min-height: 100vh;
            padding: 26px;
            display: grid;
            align-items: center;
        }

        .locale-form {
            position: fixed;
            top: 18px;
            right: 18px;
            z-index: 20;
        }

        .locale-form select {
            border: 1px solid rgba(15, 118, 110, 0.14);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.92);
            color: var(--primary-2);
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            outline: none;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
        }

        .login-shell {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(360px, 440px);
            gap: 22px;
            align-items: stretch;
        }

        .login-brand,
        .login-card {
            border-radius: 28px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.56);
            backdrop-filter: blur(8px);
        }

        .login-brand {
            position: relative;
            padding: 34px;
            background:
                radial-gradient(circle at top right, rgba(96, 165, 250, 0.24), transparent 34%),
                linear-gradient(150deg, rgba(255, 255, 255, 0.86), rgba(255, 255, 255, 0.68));
        }

        .login-brand::after {
            content: "";
            position: absolute;
            right: -60px;
            bottom: -80px;
            width: 230px;
            height: 230px;
            border-radius: 50%;
            background: rgba(15, 118, 110, 0.10);
        }

        .brand-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid rgba(15, 118, 110, 0.12);
            background: rgba(255, 255, 255, 0.94);
            color: var(--primary-2);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .35px;
            text-transform: uppercase;
        }

        .brand-chip img {
            width: 16px;
            height: 16px;
            display: block;
        }

        .brand-title {
            margin: 22px 0 0;
            font-size: clamp(34px, 4.5vw, 56px);
            line-height: 1.02;
            letter-spacing: -.04em;
            max-width: 640px;
        }

        .brand-copy {
            margin: 16px 0 0;
            max-width: 620px;
            color: var(--text-muted);
            font-size: 15px;
            line-height: 1.8;
        }

        .brand-grid {
            margin-top: 28px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .brand-stat {
            border-radius: 20px;
            padding: 18px 16px;
            background: rgba(255, 255, 255, 0.76);
            border: 1px solid rgba(15, 118, 110, 0.10);
            box-shadow: var(--shadow-sm);
        }

        .brand-stat strong {
            display: block;
            font-size: 30px;
            line-height: 1;
            color: #11352f;
        }

        .brand-stat span {
            display: block;
            margin-top: 8px;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 700;
        }

        .brand-steps {
            margin-top: 26px;
            display: grid;
            gap: 12px;
        }

        .brand-step {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 14px 16px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid rgba(15, 118, 110, 0.10);
        }

        .brand-step-index {
            width: 34px;
            height: 34px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            box-shadow: 0 10px 18px rgba(12, 80, 72, 0.22);
        }

        .brand-step strong {
            display: block;
            font-size: 14px;
        }

        .brand-step p {
            margin: 5px 0 0;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.65;
        }

        .login-card {
            padding: 30px 28px;
            background: var(--surface);
            align-self: center;
        }

        .login-card-head {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .login-mark {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            box-shadow: 0 14px 24px rgba(12, 80, 72, 0.22);
            padding: 12px;
        }

        .login-mark img {
            width: 100%;
            height: 100%;
            display: block;
        }

        .login-card h1 {
            margin: 0;
            font-size: 28px;
            line-height: 1.05;
        }

        .login-card p {
            margin: 8px 0 0;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.75;
        }

        .login-form {
            margin-top: 24px;
            display: grid;
            gap: 16px;
        }

        .field {
            display: grid;
            gap: 8px;
        }

        .field label {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .35px;
            text-transform: uppercase;
            color: #50635d;
        }

        .field-shell {
            position: relative;
        }

        .field-shell svg {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            fill: #78908a;
        }

        .field input {
            width: 100%;
            border: 1px solid #d6e4df;
            border-radius: 18px;
            background: #fff;
            color: var(--text-main);
            padding: 15px 16px 15px 46px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
            transition: .18s ease;
            box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.02);
        }

        .field input::placeholder {
            color: #93a8a2;
        }

        .field input:focus {
            border-color: #58b5a8;
            box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.12);
        }

        .submit-button {
            border: none;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            color: #fff;
            padding: 15px 18px;
            font-size: 14px;
            font-weight: 800;
            font-family: inherit;
            cursor: pointer;
            transition: .18s ease;
            box-shadow: 0 16px 28px rgba(12, 80, 72, 0.22);
        }

        .submit-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 32px rgba(12, 80, 72, 0.28);
        }

        .feedback {
            border-radius: 16px;
            padding: 12px 14px;
            font-size: 13px;
            line-height: 1.65;
            font-weight: 700;
        }

        .feedback-error {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: var(--danger);
        }

        .feedback-success {
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: var(--success);
        }

        .feedback-preview {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
        }

        .feedback-preview a {
            color: inherit;
            font-weight: 800;
            text-decoration: underline;
            word-break: break-all;
        }

        .demo-accounts {
            margin-top: 16px;
            display: grid;
            gap: 10px;
            padding: 14px;
            border-radius: 18px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .demo-accounts strong {
            font-size: 13px;
        }

        .demo-account-list {
            display: grid;
            gap: 8px;
        }

        .demo-account-item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            padding: 10px 12px;
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            font-size: 12px;
        }

        .demo-account-item span:last-child {
            color: var(--text-muted);
            font-weight: 700;
            word-break: break-all;
            text-align: right;
        }

        .login-meta {
            margin-top: 18px;
            display: grid;
            gap: 10px;
        }

        .meta-row {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 12px 14px;
            border-radius: 16px;
            background: #f7fbfa;
            border: 1px solid #e0ece8;
        }

        .meta-icon {
            width: 28px;
            height: 28px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: #e9f7f5;
            color: var(--primary);
            font-size: 13px;
            font-weight: 800;
            flex: 0 0 auto;
        }

        .meta-row strong {
            display: block;
            font-size: 13px;
        }

        .meta-row p {
            margin: 4px 0 0;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.65;
        }

        .footer-links {
            margin-top: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .footer-links span {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: #f7fbfa;
            border: 1px solid #e0ece8;
            color: #5d6f68;
            font-size: 11px;
            font-weight: 700;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        @media (max-width: 980px) {
            .page-shell {
                padding: 20px 14px;
            }

            .login-shell {
                grid-template-columns: 1fr;
            }

            .brand-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .login-brand,
            .login-card {
                border-radius: 22px;
            }

            .login-brand {
                padding: 24px 18px;
            }

            .login-card {
                padding: 24px 18px;
            }

            .brand-title {
                font-size: 34px;
            }

            .locale-form {
                top: 12px;
                right: 12px;
            }
        }
    </style>
</head>
<body class="locale-{{ app()->getLocale() === 'km' ? 'km' : 'en' }}">
    <div class="locale-form">
        <form id="login-locale-form" method="POST" action="{{ route('locale.switch') }}">
            @csrf
            <input type="hidden" name="locale" id="login-locale-value" value="{{ app()->getLocale() }}">
            <select id="locale-switch" onchange="document.getElementById('login-locale-value').value=this.value; document.getElementById('login-locale-form').submit()">
                <option value="en" {{ app()->getLocale() === 'en' ? 'selected' : '' }}>EN</option>
                <option value="km" {{ app()->getLocale() === 'km' ? 'selected' : '' }}>KM</option>
            </select>
        </form>
    </div>

    <main class="page-shell">
        <section class="login-shell">
            <aside class="login-brand">
                <span class="brand-chip">
                    <img src="{{ asset('sala-digital-mark.svg') }}" alt="">
                    {{ config('app.name', 'Sala Digital') }}
                </span>
                <h2 class="brand-title">{{ __('ui.login.portal_login') }}</h2>
                <p class="brand-copy">{{ __('ui.login.portal_text') }}</p>

                <div class="brand-steps">
                    <div class="brand-step">
                        <span class="brand-step-index">WEB</span>
                        <div>
                            <strong>{{ __('ui.login.web_access_title') }}</strong>
                            <p>{{ __('ui.login.web_access_text') }}</p>
                        </div>
                    </div>
                    <div class="brand-step">
                        <span class="brand-step-index">SEC</span>
                        <div>
                            <strong>{{ __('ui.login.secure_email_title') }}</strong>
                            <p>{{ __('ui.login.secure_email_text') }}</p>
                        </div>
                    </div>
                </div>
            </aside>

            <section class="login-card">
                <h1 class="sr-only">{{ __('ui.login.portal_login') }}</h1>

                <div class="login-card-head">
                    <div class="login-mark" aria-hidden="true">
                        <img src="{{ asset('sala-digital-mark.svg') }}" alt="Sala Digital">
                    </div>
                    <div>
                        <h1>{{ __('ui.login.portal_login') }}</h1>
                        <p>{{ __('ui.login.card_text') }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('login.submit') }}" class="login-form">
                    @csrf

                    @if (session('success'))
                        <div class="feedback feedback-success">{{ session('success') }}</div>
                    @endif

                    @if (session('status'))
                        <div class="feedback feedback-success">{{ session('status') }}</div>
                    @endif

                    @if ($showMagicLinkPreview && session('debug_magic_login_url'))
                        <div class="feedback feedback-preview">
                            <div><strong>Local preview link</strong></div>
                            <div>Email: {{ session('debug_magic_login_email') }}</div>
                            <div><a href="{{ session('debug_magic_login_path', session('debug_magic_login_url')) }}">Open login link now</a></div>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="feedback feedback-error">{{ $errors->first() }}</div>
                    @endif

                    <div class="field">
                        <label for="login">{{ __('ui.login.email_username') }}</label>
                        <div class="field-shell">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M20 5H4a2 2 0 0 0-2 2v.35l10 5.71 10-5.71V7a2 2 0 0 0-2-2Zm2 4.65-9.5 5.43a1 1 0 0 1-.99 0L2 9.65V17a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9.65Z"/>
                            </svg>
                            <input
                                id="login"
                                type="text"
                                name="login"
                                value="{{ old('login') }}"
                                placeholder="{{ __('ui.login.email_username') }}"
                                required
                                autofocus
                            >
                        </div>
                    </div>

                    <button type="submit" class="submit-button">{{ __('ui.login.submit') }}</button>
                </form>

                <div class="login-meta">
                    <div class="meta-row">
                        <span class="meta-icon">1</span>
                        <div>
                            <strong>{{ __('ui.login.magic_link_title') }}</strong>
                            <p>{{ __('ui.login.magic_link_hint') }}</p>
                        </div>
                    </div>
                    <div class="meta-row">
                        <span class="meta-icon">2</span>
                        <div>
                            <strong>{{ __('ui.login.not_registered') }}</strong>
                            <p>{{ __('ui.login.contact_admin_text') }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </section>
    </main>
</body>
</html>
