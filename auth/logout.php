<?php

require_once __DIR__ . '/../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $cancelUrl = ($_SESSION['role'] ?? '') === 'staff'
        ? '../staff/dashboard.php'
        : '../patient/dashboard.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#f5f8f7">
        <title>Log out | The Tooth Lounge</title>
        <style>
            :root { color-scheme: light; font-family: Inter, "Segoe UI", Arial, sans-serif; color: #183b3b; background: #f5f8f7; }
            * { box-sizing: border-box; }
            body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 24px; background: radial-gradient(ellipse at 50% 0%, #e3f1ed 0, transparent 55%), #f5f8f7; }
            .logout-card { width: min(100%, 460px); padding: clamp(28px, 7vw, 48px); text-align: center; background: #fff; border: 1px solid #e4eeeb; border-radius: 24px; box-shadow: 0 24px 70px rgba(27, 68, 63, .10); }
            .brand { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 34px; color: #26766d; font-size: 14px; font-weight: 750; letter-spacing: .02em; }
            .brand-mark { width: 38px; height: 38px; display: grid; place-items: center; border-radius: 12px; background: #e7f4f0; }
            .brand-mark svg { width: 22px; height: 22px; }
            .icon { width: 68px; height: 68px; display: grid; place-items: center; margin: 0 auto 22px; border-radius: 50%; color: #327e73; background: #edf7f4; }
            .icon svg { width: 30px; height: 30px; }
            h1 { margin: 0; color: #173d3b; font-size: clamp(25px, 6vw, 31px); letter-spacing: -.035em; }
            .message { margin: 12px auto 30px; color: #687d7a; font-size: 15px; line-height: 1.65; }
            .actions { display: grid; gap: 11px; }
            .button { min-height: 50px; display: inline-flex; align-items: center; justify-content: center; padding: 12px 20px; border: 1px solid transparent; border-radius: 12px; font: inherit; font-size: 15px; font-weight: 700; text-decoration: none; cursor: pointer; transition: background .16s ease, transform .16s ease, border-color .16s ease; }
            .button:hover { transform: translateY(-1px); }
            .button:focus-visible { outline: 3px solid #91cfc1; outline-offset: 3px; }
            .button-primary { color: #fff; background: #28766c; }
            .button-primary:hover { background: #1e625a; }
            .button-secondary { color: #42615e; background: #fff; border-color: #dce8e5; }
            .button-secondary:hover { background: #f6faf9; border-color: #bfd5d0; }
            .footnote { margin: 25px 0 0; color: #91a19f; font-size: 12px; }
            @media (prefers-reduced-motion: reduce) { *, *::before, *::after { transition-duration: .01ms !important; } }
        </style>
    </head>
    <body>
        <main class="logout-card">
            <div class="brand">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7.2 3.8c1.8-.8 3.1.2 4.8.2s3-1 4.8-.2c2.4 1.1 3.1 4.2 2.1 7.1-.8 2.2-1.8 4.6-2.7 6.9-.6 1.4-1.2 2.4-2.1 2.4-1.4 0-1.3-4.2-2.1-4.2s-.7 4.2-2.1 4.2c-.9 0-1.5-1-2.1-2.4-.9-2.3-1.9-4.7-2.7-6.9-1-2.9-.3-6 2.1-7.1Z"/></svg>
                </span>
                <span>The Tooth Lounge</span>
            </div>
            <div class="icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg>
            </div>
            <h1>Ready to log out?</h1>
            <p class="message">You’ll end your current session and return to the sign-in page. You can log back in anytime.</p>
            <div class="actions">
                <form method="POST">
                    <?= csrf_field() ?>
                    <button class="button button-primary" type="submit" style="width:100%">Yes, log me out</button>
                </form>
                <a class="button button-secondary" href="<?= h($cancelUrl) ?>">Stay signed in</a>
            </div>
            <p class="footnote">Your account security matters to us.</p>
        </main>
    </body>
    </html>
    <?php
    exit;
}

csrf_verify();

audit_log('logout');

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

session_destroy();

header('Location: login.php');
exit;
