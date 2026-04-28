<?php
// Trailhead Setup — One-time first user creation
// DELETE THIS FILE after creating your account

define('APP_ROOT', dirname(__DIR__));

// Load .env
if (file_exists(APP_ROOT . '/.env')) {
    $lines = file(APP_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
        $_SERVER[trim($name)] = trim($value);
    }
}

// DB connection
$host = $_ENV['DB_HOST'] ?? 'localhost';
$name = $_ENV['DB_NAME'] ?? '';
$user = $_ENV['DB_USER'] ?? '';
$pass = $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('<p style="color:red">Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

// Create users table if it doesn't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username     = trim($_POST['username'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm'] ?? '';

    if (!$username || !$display_name || !$password) {
        $message = 'All fields are required.';
    } elseif ($password !== $confirm) {
        $message = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
    } else {
        // Check if username already exists
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $check->execute([$username]);
        if ($check->fetch()) {
            $message = 'Username already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, display_name, password) VALUES (?, ?, ?)');
            $stmt->execute([$username, $display_name, $hash]);
            $success = true;
            $message = 'User created successfully! You can now <a href="/login">sign in</a>. <strong>Please delete setup.php from the server now.</strong>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trailhead Setup</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f3f4f6; color: #222; display: grid; place-items: center; min-height: 100vh; margin: 0; }
        .card { width: min(440px, 92vw); background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        h1 { margin-top: 0; }
        .badge { display: inline-block; background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; border-radius: 6px; padding: 0.3rem 0.75rem; font-size: 0.85rem; font-weight: 600; margin-bottom: 1rem; }
        label { display: block; margin: 1rem 0 0.4rem; font-weight: 600; }
        input { width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 8px; box-sizing: border-box; }
        button { margin-top: 1.25rem; width: 100%; padding: 0.85rem; border: 0; border-radius: 8px; background: #0f766e; color: #fff; font-weight: 700; cursor: pointer; font-size: 1rem; }
        button:hover { background: #0d5e57; }
        .message { margin-top: 1rem; padding: 0.75rem 1rem; border-radius: 8px; }
        .message.error { background: #fee2e2; color: #b00020; }
        .message.success { background: #d1fae5; color: #065f46; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">⚠ One-Time Setup</div>
        <h1>Create Admin User</h1>
        <p>Fill in the details below to create the first Trailhead user. Delete this file after setup.</p>

        <?php if ($message): ?>
            <div class="message <?= $success ? 'success' : 'error' ?>"><?= $message ?></div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="post">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">

            <label for="display_name">Display Name</label>
            <input id="display_name" name="display_name" type="text" required value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>">

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required minlength="8">

            <label for="confirm">Confirm Password</label>
            <input id="confirm" name="confirm" type="password" required minlength="8">

            <button type="submit">Create User</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
