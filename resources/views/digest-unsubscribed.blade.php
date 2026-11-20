<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Unsubscribed - BookLoop</title>
    <style>
        body { margin: 0; background: #faf6ef; color: #1a1612; font-family: 'Segoe UI', system-ui, sans-serif; }
        .box { max-width: 460px; margin: 15vh auto 0; padding: 32px 24px; background: #fff; border: 1px solid #e5ddd0; border-radius: 8px; text-align: center; }
        h1 { font-family: Georgia, serif; color: #3a6b3e; font-size: 24px; margin: 0 0 12px; }
        p { color: #7a7068; line-height: 1.5; }
        a { display: inline-block; margin-top: 12px; padding: 10px 22px; background: #3a6b3e; color: #fff; border-radius: 6px; text-decoration: none; font-weight: bold; }
        @media (prefers-color-scheme: dark) {
            body { background: #1a1612; }
            .box { background: #262019; border-color: #3d3630; }
            p { color: #e5ddd0; }
        }
    </style>
</head>
<body>
    <div class="box">
        <h1>📚 You are unsubscribed</h1>
        <p>You will not get the weekly roundup anymore.<br>Pārskatu vairs nesūtīsim.</p>
        <p>You can turn it back on any time in your settings.</p>
        <a href="{{ url('/settings') }}">Go to BookLoop</a>
    </div>
</body>
</html>
