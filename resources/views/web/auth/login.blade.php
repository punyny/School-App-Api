<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | {{ config('app.name', 'School API') }}</title>
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
            background: linear-gradient(135deg, #c5d8e6 0%, #e3d2e6 50%, #c4d3e8 100%);
            overflow: hidden;
            position: relative;
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #ffffff 0%, #f4effa 20%, #d8cde6 60%, #a295b8 100%);
            box-shadow:
                inset -10px -10px 20px rgba(0, 0, 0, 0.1),
                0 15px 35px rgba(0, 0, 0, 0.15);
            animation: floatOrb ease-in-out infinite;
            z-index: 0;
        }

        .orb:nth-child(1) { width: 150px; height: 150px; top: 10%; left: 15%; animation-duration: 8s; }
        .orb:nth-child(2) { width: 80px; height: 80px; top: 20%; left: 70%; animation-duration: 6s; animation-delay: 1s; }
        .orb:nth-child(3) { width: 200px; height: 200px; bottom: 10%; right: 10%; animation-duration: 10s; animation-delay: 2s; }
        .orb:nth-child(4) { width: 120px; height: 120px; bottom: 20%; left: 25%; animation-duration: 7s; animation-delay: 0.5s; }
        .orb:nth-child(5) { width: 60px; height: 60px; top: 40%; right: 25%; animation-duration: 5s; animation-delay: 1.5s; }
        .orb:nth-child(6) { width: 90px; height: 90px; top: 60%; left: 5%; animation-duration: 9s; animation-delay: 3s; filter: blur(2px); }

        @keyframes floatOrb {
            0%, 100% { transform: translateY(0) translateX(0); }
            50% { transform: translateY(-25px) translateX(10px); }
        }

        .glass-panel {
            position: relative;
            z-index: 10;
            width: min(420px, 90%);
            padding: 40px 30px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 24px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.1);
            text-align: center;
            color: #ffffff;
            animation: slideUpFade 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes slideUpFade {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .avatar-container {
            width: 70px;
            height: 70px;
            margin: 0 auto 24px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
        }

        .avatar-container svg {
            width: 35px;
            height: 35px;
            fill: #ffffff;
        }

        .input-group {
            position: relative;
            margin-bottom: 16px;
        }

        .input-group svg {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            fill: #ffffff;
            opacity: 0.8;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px 12px 45px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 30px;
            color: #ffffff;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: all 0.3s ease;
        }

        input::placeholder {
            color: rgba(255, 255, 255, 0.8);
        }

        input:focus {
            background: rgba(255, 255, 255, 0.25);
            border-color: #ffffff;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.3);
        }

        .row-options {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            margin-bottom: 24px;
            color: rgba(255, 255, 255, 0.9);
        }

        .row-options input[type="checkbox"] {
            accent-color: #a295b8;
            cursor: pointer;
        }

        button {
            width: 60%;
            padding: 12px;
            background: #ffffff;
            color: #8c7ba0;
            border: none;
            border-radius: 30px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 255, 255, 0.4);
            background: #fdfdfd;
        }

        .locale-form {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 20;
        }

        .locale-form select {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            color: #fff;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            outline: none;
            cursor: pointer;
        }

        .locale-form select option {
            color: #333;
        }

        .error {
            background: rgba(255, 50, 50, 0.2);
            border: 1px solid rgba(255, 50, 50, 0.4);
            color: #fff;
            padding: 10px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .footer-links {
            margin-top: 24px;
            font-size: 12px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: #fff;
            text-decoration: underline;
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
    </style>
</head>
<body class="locale-{{ app()->getLocale() }}">

    <div class="orb"></div>
    <div class="orb"></div>
    <div class="orb"></div>
    <div class="orb"></div>
    <div class="orb"></div>
    <div class="orb"></div>

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

    <div class="glass-panel">
        <h1 class="sr-only">{{ __('ui.login.portal_login') }}</h1>

        <div class="avatar-container">
            <svg viewBox="0 0 24 24">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
        </div>

        <form method="POST" action="{{ route('login.submit') }}">
            @csrf

            <div class="input-group">
                <svg viewBox="0 0 24 24">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
                <input id="login" type="text" name="login" value="{{ old('login') }}" placeholder="{{ __('ui.login.email_username') }}" required autofocus>
            </div>

            <div class="input-group">
                <svg viewBox="0 0 24 24">
                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                </svg>
                <input id="password" type="password" name="password" placeholder="{{ __('ui.login.password') }}" required>
            </div>

            <label class="row-options" for="remember">
                <input id="remember" type="checkbox" name="remember" value="1">
                <span>{{ __('ui.login.remember') }}</span>
            </label>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <button type="submit">{{ __('ui.login.submit') }}</button>
        </form>

        <div class="footer-links">
            <a href="#">{{ __('ui.login.forgot_password') }}</a>
            <a href="#">{{ __('ui.login.not_registered') }}</a>
        </div>
    </div>

</body>
</html>
