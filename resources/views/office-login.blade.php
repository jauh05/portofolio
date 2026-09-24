<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Living Office - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #071426;
            color: #e2e8f0;
            font-family: 'DM Sans', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-image: radial-gradient(circle at 50% 0%, #1e3a8a 0%, transparent 60%);
        }
        .login-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 40px;
            border-radius: 16px;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .login-header h1 span {
            color: #22d3ee;
        }
        .login-header p {
            color: #94a3b8;
            font-size: 13px;
            margin: 8px 0 0 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #cbd5e1;
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #f8fafc;
            font-family: inherit;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #22d3ee;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        .error {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h1>Living <span>Office</span></h1>
            <p>Private Operations Dashboard</p>
        </div>

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
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn">Access Dashboard</button>
        </form>
    </div>
</body>
</html>
