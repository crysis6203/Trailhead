<?php
// Dashboard controller

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trailhead Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; background: #f7f7f7; color: #222; }
        .wrap { max-width: 900px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem; }
        h1, h2 { margin-top: 0; }
        .actions a { display: inline-block; margin-right: 1rem; color: #0a5; text-decoration: none; }
        .actions a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Trailhead Dashboard</h1>
        <p>Welcome back. This is the starting point for managing scouting advancement sessions.</p>
        <div class="actions">
            <a href="/sessions">Sessions</a>
            <a href="/scouts">Scouts</a>
            <a href="/queue">Queue</a>
            <a href="/run">Run</a>
            <a href="/logout">Logout</a>
        </div>
    </div>

    <div class="card">
        <h2>Coming next</h2>
        <p>This dashboard will soon show active sessions, recent run history, and queue counts.</p>
    </div>
</div>
</body>
</html>
