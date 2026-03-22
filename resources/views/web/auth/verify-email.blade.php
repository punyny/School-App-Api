<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification | {{ config('app.name', 'Sala Digital') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('sala-digital-mark.svg') }}">
    <link rel="shortcut icon" href="{{ asset('sala-digital-mark.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=Kantumruy+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Sora", "Kantumruy Pro", sans-serif;
            background: linear-gradient(135deg, #d8e6cf 0%, #f0dfc8 50%, #d3dde7 100%);
            color: #fff;
            padding: 24px;
        }

        .panel {
            width: min(520px, 100%);
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 24px;
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.12);
            padding: 36px 30px;
        }

        h1 {
            font-size: 30px;
            margin-bottom: 12px;
        }

        p {
            color: rgba(255, 255, 255, 0.92);
            line-height: 1.6;
        }

        .meta {
            margin-top: 18px;
            padding: 16px 18px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.12);
        }

        .status {
            margin-top: 18px;
            padding: 12px 14px;
            border-radius: 14px;
            background: rgba(70, 166, 106, 0.28);
            border: 1px solid rgba(173, 240, 191, 0.4);
        }

        .actions {
            margin-top: 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        button,
        a {
            border: none;
            border-radius: 999px;
            padding: 12px 18px;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        button {
            background: #fff;
            color: #4d6f58;
        }

        a {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.28);
        }
    </style>
</head>
<body>
    <section class="panel">
        <h1>Verify your email</h1>
        <p>Your account is almost ready. Please open the verification link we sent before trying to use the dashboard.</p>

        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        <div class="meta">
            <p><strong>Email:</strong> {{ $user?->email ?? '-' }}</p>
            <p style="margin-top:8px;">If you do not see the email, check spam/junk or send a new verification link.</p>
        </div>

        <div class="actions">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit">Resend verification email</button>
            </form>

            <a href="{{ route('logout') }}"
               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Sign out</a>
        </div>

        <form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none;">
            @csrf
        </form>
    </section>
</body>
</html>
