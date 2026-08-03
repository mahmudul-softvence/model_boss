<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Model Boss') }} — Coming Soon</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Manrope:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>

<body>
    <div class="stage">
        <div class="glow glow-a" aria-hidden="true"></div>
        <div class="glow glow-b" aria-hidden="true"></div>
        <div class="grid" aria-hidden="true"></div>

        <main class="content">
            <p class="brand">{{ config('app.name', 'Model Boss') }}</p>
            <h1>We’re building something worth the wait.</h1>
            <p class="lede">This site is under development. Check back soon for the full experience.</p>
            <div class="status" role="status">
                <span class="pulse" aria-hidden="true"></span>
                <span>In progress</span>
            </div>
        </main>
    </div>
</body>

</html>
