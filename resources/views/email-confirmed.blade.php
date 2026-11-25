<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $ok ? 'Email confirmed' : 'Link not valid' }} - BookLoop</title>
    <style>
        body { margin: 0; background: #faf6ef; color: #1a1612; font-family: 'Segoe UI', system-ui, sans-serif; }
        .box { max-width: 460px; margin: 15vh auto 0; padding: 32px 24px; background: #fff; border: 1px solid #e5ddd0; border-radius: 8px; text-align: center; }
        h1 { font-family: Georgia, serif; color: #3a6b3e; font-size: 24px; margin: 0 0 12px; }
        h1.bad { color: #a8452f; }
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
        @if ($ok)
            <h1>📚 Email confirmed</h1>
            <p>From now on your BookLoop email is <strong>{{ $email }}</strong>.<br>E-pasts nomainīts.</p>
        @elseif ($reason === 'taken')
            <h1 class="bad">This email is already used</h1>
            <p>Another account already uses this email, so your email was not changed.<br>Šis e-pasts jau ir aizņemts.</p>
        @else
            <h1 class="bad">This link is not valid anymore</h1>
            <p>Maybe it is already used or you asked for another email later. Change the email again in your settings.<br>Saite vairs neder.</p>
        @endif
        <a href="{{ url('/settings') }}">Go to BookLoop</a>
    </div>
</body>
</html>
