<?php
// Sessions controller

if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }

// Create tables if not exist
$pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    session_date DATE NOT NULL,
    notes TEXT DEFAULT NULL,
    status ENUM('open','closed') DEFAULT 'open',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS session_scouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    scout_id INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $date = trim($_POST['session_date'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $scout_ids = $_POST['scout_ids'] ?? [];

        if ($name && $date) {
            $stmt = $pdo->prepare('INSERT INTO sessions (name, session_date, notes, created_by) VALUES (?,?,?,?)');
            $stmt->execute([$name, $date, $notes ?: null, $_SESSION['user_id']]);
            $session_id = $pdo->lastInsertId();

            foreach ($scout_ids as $sid) {
                $sid = (int)$sid;
                if ($sid) {
                    $pdo->prepare('INSERT INTO session_scouts (session_id, scout_id) VALUES (?,?)')->execute([$session_id, $sid]);
                }
            }
            $message = 'Session created! <a href="/queue?session_id=' . $session_id . '">Add advancements &rarr;</a>';
        } else {
            $error = 'Session name and date are required.';
        }
    }

    if ($action === 'close') {
        $id = (int)($_POST['session_id'] ?? 0);
        if ($id) {
            $pdo->prepare('UPDATE sessions SET status = ? WHERE id = ?')->execute(['closed', $id]);
            $message = 'Session closed.';
        }
    }

    if ($action === 'reopen') {
        $id = (int)($_POST['session_id'] ?? 0);
        if ($id) {
            $pdo->prepare('UPDATE sessions SET status = ? WHERE id = ?')->execute(['open', $id]);
            $message = 'Session reopened.';
        }
    }
}

$sessions = $pdo->query('
    SELECT s.*, u.display_name,
        (SELECT COUNT(*) FROM session_scouts ss WHERE ss.session_id = s.id) AS scout_count,
        (SELECT COUNT(*) FROM queue_items qi WHERE qi.session_id = s.id) AS queue_count
    FROM sessions s
    JOIN users u ON u.id = s.created_by
    ORDER BY s.session_date DESC, s.id DESC
')->fetchAll();

$scouts = $pdo->query('SELECT * FROM scouts WHERE active = 1 ORDER BY last_name, first_name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sessions — Trailhead</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; background: #f7f7f7; color: #222; }
        header { background: #0f766e; color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-left: 1rem; font-size: 0.95rem; }
        .wrap { max-width: 960px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; }
        h1, h2 { margin-top: 0; }
        label { display: block; margin: 0.75rem 0 0.3rem; font-weight: 600; font-size: 0.9rem; }
        input, select, textarea { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.95rem; }
        textarea { height: 80px; resize: vertical; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        button { padding: 0.65rem 1.25rem; border: 0; border-radius: 6px; background: #0f766e; color: #fff; font-weight: 700; cursor: pointer; margin-top: 1rem; }
        button:hover { background: #0d5e57; }
        .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.85rem; margin-top: 0; }
        .btn-muted { background: #6b7280; }
        .btn-muted:hover { background: #4b5563; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 0.6rem 0.75rem; background: #f3f4f6; border-bottom: 2px solid #ddd; font-size: 0.85rem; }
        td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #eee; font-size: 0.9rem; vertical-align: middle; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-gray { background: #f3f4f6; color: #6b7280; }
        .msg { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .msg-success { background: #d1fae5; color: #065f46; }
        .msg-error { background: #fee2e2; color: #b00020; }
        .scout-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.5rem; margin-top: 0.5rem; }
        .scout-list label { font-weight: normal; display: flex; align-items: center; gap: 0.4rem; margin: 0; cursor: pointer; }
        .action-group { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        a.btn-link { display: inline-block; padding: 0.35rem 0.75rem; background: #0f766e; color: #fff; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; }
        a.btn-link:hover { background: #0d5e57; }
    </style>
</head>
<body>
<header>
    <strong>Trailhead</strong>
    <div>
        <a href="/">Dashboard</a>
        <a href="/sessions">Sessions</a>
        <a href="/scouts">Scouts</a>
        <a href="/logout">Logout</a>
    </div>
</header>
<div class="wrap">
    <?php if ($message): ?><div class="msg msg-success"><?= $message ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card">
        <h2>New Session</h2>
        <?php if (empty($scouts)): ?>
            <p style="color:#888">No active scouts found. <a href="/scouts">Add scouts first &rarr;</a></p>
        <?php else: ?>
        <form method="post">
            <input type="hidden" name="action" value="create">
            <div class="row">
                <div>
                    <label>Session Name *</label>
                    <input name="name" type="text" placeholder="e.g. April Court of Honor" required>
                </div>
                <div>
                    <label>Date *</label>
                    <input name="session_date" type="date" required value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <label>Notes</label>
            <textarea name="notes" placeholder="Optional notes"></textarea>
            <label>Scouts Attending</label>
            <div class="scout-list">
                <?php foreach ($scouts as $s): ?>
                <label>
                    <input type="checkbox" name="scout_ids[]" value="<?= $s['id'] ?>">
                    <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>
                </label>
                <?php endforeach; ?>
            </div>
            <button type="submit">Create Session</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>All Sessions</h2>
        <?php if (empty($sessions)): ?>
            <p style="color:#888">No sessions yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Date</th>
                    <th>Scouts</th>
                    <th>Queue</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sessions as $sess): ?>
                <tr>
                    <td><?= htmlspecialchars($sess['name']) ?></td>
                    <td><?= htmlspecialchars($sess['session_date']) ?></td>
                    <td><?= $sess['scout_count'] ?></td>
                    <td><?= $sess['queue_count'] ?></td>
                    <td><span class="badge <?= $sess['status'] === 'open' ? 'badge-green' : 'badge-gray' ?>"><?= ucfirst($sess['status']) ?></span></td>
                    <td>
                        <div class="action-group">
                            <a class="btn-link" href="/queue?session_id=<?= $sess['id'] ?>">Queue</a>
                            <a class="btn-link" href="/run?session_id=<?= $sess['id'] ?>">Run</a>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="session_id" value="<?= $sess['id'] ?>">
                                <?php if ($sess['status'] === 'open'): ?>
                                    <input type="hidden" name="action" value="close">
                                    <button class="btn-sm btn-muted">Close</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="reopen">
                                    <button class="btn-sm">Reopen</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
