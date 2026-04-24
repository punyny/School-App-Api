<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open Sala Digital</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('sala-digital-mark.svg') }}">
    <link rel="shortcut icon" href="{{ asset('sala-digital-mark.svg') }}">
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f7fb;
            --card: rgba(255, 255, 255, 0.96);
            --text: #13313b;
            --muted: #56727a;
            --primary: #1f7a8c;
            --primary-dark: #145866;
            --border: rgba(19, 49, 59, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Noto Sans", "Noto Sans Khmer", system-ui, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(31, 122, 140, 0.18), transparent 34%),
                linear-gradient(180deg, #edf8fb 0%, var(--bg) 100%);
            color: var(--text);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .card {
            width: min(100%, 520px);
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 28px;
            box-shadow: 0 18px 50px rgba(19, 49, 59, 0.12);
            padding: 28px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            background: rgba(31, 122, 140, 0.12);
            color: var(--primary-dark);
            padding: 8px 14px;
            font-size: 14px;
            font-weight: 700;
        }

        h1 {
            margin: 18px 0 10px;
            font-size: clamp(28px, 5vw, 38px);
            line-height: 1.1;
        }

        p {
            margin: 0 0 14px;
            line-height: 1.6;
            color: var(--muted);
        }

        .actions {
            display: grid;
            gap: 12px;
            margin: 24px 0 16px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 52px;
            border-radius: 16px;
            text-decoration: none;
            font-weight: 700;
            padding: 14px 18px;
            transition: transform 0.16s ease, opacity 0.16s ease;
        }

        .button:hover {
            transform: translateY(-1px);
        }

        .button-primary {
            background: linear-gradient(135deg, var(--primary) 0%, #2da0b7 100%);
            color: #fff;
        }

        .button-secondary {
            background: #fff;
            border: 1px solid var(--border);
            color: var(--text);
        }

        .hint {
            border-top: 1px solid var(--border);
            margin-top: 18px;
            padding-top: 18px;
            font-size: 14px;
        }

        .status {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary-dark);
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div id="web-react-shell-root" data-shell="guest"></div>
    <div id="web-legacy-content">
    <main class="card">
        <div class="badge">School Mobile</div>
        <h1>Open the app to finish sign-in</h1>
        <p>We are opening the School mobile app for you now. If nothing happens, tap the button below.</p>
        <div class="actions">
            <a class="button button-primary" href="{{ $mobileLoginUrl }}">Open School App</a>
            <a class="button button-secondary" href="{{ $webFallbackUrl }}">Continue on Web Instead</a>
        </div>
        <p class="status" id="status-text">Trying to open the app...</p>
        <p class="hint">This secure sign-in link expires in {{ $expiresInMinutes }} minutes and can only be used once.</p>
    </main>

    <script>
        window.setTimeout(function () {
            window.location.replace(@json($mobileLoginUrl));
        }, 120);

        window.setTimeout(function () {
            var status = document.getElementById('status-text');
            if (status) {
                status.textContent = 'If the app did not open, tap "Open School App" above.';
            }
        }, 1800);
    </script>
    </div>
    @vite('resources/js/react-web-shell.jsx')
</body>
</html>
