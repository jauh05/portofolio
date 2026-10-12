<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Living Office - Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #102e5c;
            --text: #42526b;
            --muted: #667085;
            --blue: #2e90fa;
            --blue-dark: #1570ef;
            --soft: #eaf3ff;
            --line: #d9e8fb;
            --ease: cubic-bezier(.22,1,.36,1);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 40px 24px;
            font-family: Inter, system-ui, sans-serif;
            color: var(--text);
            line-height: 1.6;
            background: radial-gradient(circle at 86% 8%, #e9f3ff 0, transparent 32%), linear-gradient(#fff 65%, #f8fbff);
            overflow-x: hidden;
        }
        button, input { font: inherit; }

        .login-shell { width: 100%; max-width: 1040px; display: grid; grid-template-columns: 1.02fr .98fr; border: 1px solid var(--line); border-radius: 28px; overflow: hidden; background: #fff; box-shadow: 0 30px 80px -40px rgba(15,46,92,.45); }

        .login-hero { position: relative; display: flex; flex-direction: column; gap: 28px; padding: 44px 44px 36px; overflow: hidden; color: #fff; background: linear-gradient(145deg, var(--blue), var(--blue-dark)); }
        .login-hero:after { content: ''; position: absolute; right: -120px; top: -120px; width: 320px; height: 320px; border-radius: 50%; background: rgba(255,255,255,.08); pointer-events: none; }
        .logo { width: 43px; height: 43px; flex: none; border-radius: 14px; display: grid; place-items: center; font: 800 16px Sora, sans-serif; }
        .login-hero .logo { background: #fff; color: var(--blue-dark); box-shadow: 0 10px 20px -10px rgba(0,0,0,.25); }
        .eyebrow { display: inline-flex; gap: 9px; align-items: center; color: #dbeafe; font: 600 12px 'JetBrains Mono', monospace; letter-spacing: .13em; text-transform: uppercase; margin-bottom: 16px; }
        .eyebrow:before { content: ''; width: 22px; height: 1px; background: #dbeafe; }
        .login-hero h1 { font: 800 clamp(2.2rem, 3.6vw, 3.2rem)/1.04 Sora, sans-serif; letter-spacing: -.04em; color: #fff; margin-bottom: 14px; }
        .login-lead { max-width: 360px; font-size: 15px; line-height: 1.7; color: #e6f1ff; }
        .login-art { display: block; width: 100%; max-width: 360px; margin-top: auto; }

        .login-form-panel { display: flex; flex-direction: column; justify-content: center; padding: 48px 44px; }
        .login-form-panel h2 { font: 800 24px/1.2 Sora, sans-serif; letter-spacing: -.02em; color: var(--ink); }
        .login-sub { margin: 6px 0 28px; font-size: 13px; color: var(--muted); }
        .error { background: #fef3f2; color: #b42318; border: 1px solid #fecdca; border-radius: 12px; padding: 11px 14px; font-size: 13px; margin-bottom: 20px; }
        .form-group { margin-bottom: 22px; }
        .form-group label { display: block; margin-bottom: 2px; color: var(--muted); font-size: 12px; font-weight: 600; }
        .field { position: relative; }
        .form-group input { width: 100%; padding: 10px 0; border: 0; border-bottom: 1px solid var(--line); border-radius: 0; background: transparent; color: var(--ink); font-size: 15px; outline: none; transition: border-color .2s var(--ease); }
        .form-group input:focus { border-bottom-color: var(--blue); }
        .form-group input[type="password"] { padding-right: 56px; }
        .toggle-visibility { position: absolute; right: 0; bottom: 11px; border: 0; background: none; color: var(--blue-dark); font-size: 12px; font-weight: 600; cursor: pointer; }
        .btn { display: inline-flex; align-items: center; justify-content: center; width: 100%; margin-top: 8px; padding: 14px 24px; border: 0; border-radius: 100px; background: var(--blue); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; box-shadow: 0 10px 24px -12px var(--blue-dark); transition: .25s var(--ease); }
        .btn:hover { background: var(--blue-dark); transform: translateY(-2px); }
        .btn:focus-visible, .toggle-visibility:focus-visible, .login-foot a:focus-visible { outline: 3px solid #78b4fa; outline-offset: 2px; }
        .login-foot { margin-top: 26px; text-align: center; font-size: 13px; color: var(--muted); }
        .login-foot a { color: var(--blue-dark); font-weight: 600; text-decoration: none; }
        .login-foot a:hover { text-decoration: underline; }

        @media (max-width: 900px) {
            .login-shell { grid-template-columns: 1fr; max-width: 460px; }
            .login-hero { gap: 18px; padding: 32px 28px 28px; }
            .login-hero h1 { font-size: 2.4rem; }
            .login-lead { font-size: 14px; }
            .login-art { max-width: 260px; }
            .login-form-panel { padding: 32px 28px 36px; }
        }
        @media (max-width: 600px) {
            .login-art { display: none; }
        }
        @media (max-width: 480px) {
            body { padding: 16px; }
            .login-form-panel { padding: 26px 22px 30px; }
            .login-hero { padding: 26px 22px 22px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; }
        }
    </style>
</head>
<body>
    <main class="login-shell">
        <section class="login-hero">
            <span class="logo" aria-hidden="true">JF</span>
            <div>
                <span class="eyebrow">Private access</span>
                <h1>Living AI Office</h1>
                <p class="login-lead">Living AI Office untuk memantau Trent dan logical workers JaukiContentBot.</p>
            </div>
            <svg class="login-art" viewBox="0 0 360 240" aria-hidden="true" focusable="false">
                <line x1="20" y1="212" x2="340" y2="212" stroke="#fff" stroke-opacity=".4" stroke-width="2" stroke-linecap="round"/>
                <rect x="40" y="26" width="200" height="132" rx="18" fill="#fff" fill-opacity=".14" stroke="#fff" stroke-opacity=".4" stroke-width="2"/>
                <rect x="58" y="46" width="22" height="78" rx="6" fill="#fff" fill-opacity=".9"/>
                <rect x="90" y="70" width="22" height="54" rx="6" fill="#fff" fill-opacity=".55"/>
                <rect x="122" y="56" width="22" height="68" rx="6" fill="#fff" fill-opacity=".75"/>
                <rect x="154" y="84" width="22" height="40" rx="6" fill="#fff" fill-opacity=".45"/>
                <rect x="186" y="62" width="22" height="62" rx="6" fill="#fff" fill-opacity=".7"/>
                <polyline points="206,104 220,92 232,98 244,80 256,86" fill="none" stroke="#bfe0ff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                <rect x="130" y="158" width="20" height="30" fill="#fff" fill-opacity=".35"/>
                <rect x="104" y="184" width="72" height="8" rx="4" fill="#fff" fill-opacity=".5"/>
                <rect x="254" y="120" width="30" height="92" rx="6" fill="#fff" fill-opacity=".22"/>
                <rect x="290" y="88" width="34" height="124" rx="6" fill="#fff" fill-opacity=".3"/>
                <rect x="328" y="142" width="22" height="70" rx="6" fill="#fff" fill-opacity=".18"/>
                <rect x="262" y="36" width="92" height="30" rx="15" fill="#fff"/>
                <circle cx="280" cy="51" r="5" fill="#20b26b"/>
                <rect x="292" y="47" width="50" height="8" rx="4" fill="#bfd9f7"/>
            </svg>
        </section>

        <section class="login-form-panel">
            <h2>Owner Sign In</h2>
            <p class="login-sub">Private Operations Dashboard</p>

            @if($errors->any())
                <div class="error">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="/office/login">
                @csrf
                <div class="form-group">
                    <label for="email">Owner Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                </div>
                <div class="form-group field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                    <button type="button" class="toggle-visibility" id="togglePassword" aria-controls="password" aria-pressed="false">Show</button>
                </div>
                <button type="submit" class="btn">Access Dashboard</button>
            </form>

            <p class="login-foot">Owner access only · <a href="/">Kembali ke portofolio</a></p>
        </section>
    </main>
    <script>
        (function () {
            var input = document.getElementById('password');
            var toggle = document.getElementById('togglePassword');
            toggle.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                toggle.textContent = show ? 'Hide' : 'Show';
                toggle.setAttribute('aria-pressed', String(show));
            });
        })();
    </script>
</body>
</html>
