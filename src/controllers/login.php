<?php
// Login controller

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['display_name'] = $user['display_name'];
            header('Location: /');
            exit;
        }

        $error = 'Invalid username or password.';
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trailhead Login</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f3f4f6; color: #222; display: grid; place-items: center; min-height: 100vh; margin: 0; }
        .card { width: min(420px, 92vw); background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        h1 { margin-top: 0; }
        label { display: block; margin: 1rem 0 0.4rem; font-weight: 600; }
        input { width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 8px; }
        button { margin-top: 1rem; width: 100%; padding: 0.85rem; border: 0; border-radius: 8px; background: #0f766e; color: #fff; font-weight: 700; cursor: pointer; }
        .error { color: #b00020; margin-top: 1rem; }
        .hint { color: #666; font-size: 0.95rem; margin-top: 1rem; }
    </style>
</head>
<body>
    <form class="card" method="post" action="/login">
        <h1>Trailhead</h1>
        <p>Sign in to manage scouting advancement sessions.</p>

        <label for="username">Username</label>
        <input id="username" name="username" type="text" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <button type="submit">Sign in</button>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="hint">First user accounts can be inserted directly into the <code>users</code> table for now.</div>
    </form>
</body>
</html>
