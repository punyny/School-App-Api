<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Continue Sign In | {{ config('app.name', 'Sala Digital') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('sala-digital-mark.svg') }}">
    <link rel="shortcut icon" href="{{ asset('sala-digital-mark.svg') }}">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: "Sora", "Kantumruy Pro", sans-serif;
            color: #182620;
            background:
                radial-gradient(860px 420px at -10% -12%, #dbeafe 0%, transparent 70%),
                radial-gradient(860px 420px at 110% 110%, #fde68a 0%, transparent 68%),
                linear-gradient(140deg, #f6f1e6 0%, #fff3e5 40%, #e9f2f4 100%);
        }
        .card {
            width: min(520px, 100%);
            border-radius: 28px;
            padding: 32px;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid rgba(15, 118, 110, 0.14);
            box-shadow: 0 30px 60px rgba(21, 46, 42, 0.16);
        }
        .brand {
            width: 72px;
            height: 72px;
            border-radius: 22px;
            padding: 14px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #0f766e, #0b4c45);
            box-shadow: 0 14px 24px rgba(12, 80, 72, 0.22);
        }
        .brand img { width: 100%; height: 100%; display: block; }
        h1 {
            margin: 22px 0 0;
            font-size: 34px;
            line-height: 1.05;
        }
        p {
            margin: 12px 0 0;
            color: #5b6b65;
            line-height: 1.75;
            font-size: 15px;
        }
        .meta {
            margin-top: 18px;
            padding: 16px 18px;
            border-radius: 18px;
            background: #f7fbfa;
            border: 1px solid #d9e2de;
        }
        .meta strong {
            display: block;
            font-size: 13px;
            color: #0b4c45;
            text-transform: uppercase;
            letter-spacing: .45px;
        }
        .meta span {
            display: block;
            margin-top: 8px;
            font-size: 16px;
            color: #182620;
            font-weight: 700;
        }
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
        }
        .button {
            appearance: none;
            border: 0;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 52px;
            padding: 0 18px;
            border-radius: 16px;
            font-weight: 800;
            font-size: 15px;
        }
        .button-primary {
            background: linear-gradient(135deg, #0f766e, #0b4c45);
            color: #fff;
            box-shadow: 0 16px 28px rgba(12, 80, 72, 0.22);
        }
        .button-secondary {
            background: #fff;
            color: #182620;
            border: 1px solid #d9e2de;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="brand">
            <img src="{{ asset('sala-digital-mark.svg') }}" alt="Sala Digital">
        </div>
        <h1>Continue sign in</h1>
        <p>
            Your email link is ready. To protect your account from automatic email scanners,
            please confirm sign in below.
        </p>

        <div class="meta">
            <strong>Account</strong>
            <span>{{ $user->email }}</span>
            @if ($expiresAt)
                <strong style="margin-top: 14px;">Link expires at</strong>
                <span>{{ $expiresAt }}</span>
            @endif
        </div>

        <div class="actions">
            <form method="POST" action="{{ $consumeUrl }}">
                @csrf
                <button type="submit" class="button button-primary">Sign in now</button>
            </form>
            <a href="{{ route('login') }}" class="button button-secondary">Back to login</a>
        </div>
    </main>
</body>
</html>
