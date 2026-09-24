<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#071426">
    <title>Living AI Office — Jauhar Bot HQ</title>
    <meta name="description" content="Living AI Office untuk memantau Trent dan logical workers JaukiContentBot.">
    <link rel="icon" type="image/png" href="{{ asset('favicon_jf.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    @vite('resources/js/living-office/main.jsx')
</head>
<body>
    <div id="living-office-root"></div>
</body>
</html>
