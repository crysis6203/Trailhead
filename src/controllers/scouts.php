<?php
// Scouts controller

if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }

// Create table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS scouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    bsa_id VARCHAR(50) DEFAULT NULL,
    rank VARCHAR(100) DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$message = '';
$error   = '';

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name'] ?? '');
        $bsa   = trim($_POST['bsa_id'] ?? '');
        $rank  = trim($_POST['rank'] ?? '');
        if ($first && $last) {
            $stmt = $pdo->prepare('INSERT INTO scouts (first_name, last_name, bsa_id, rank) VALUES (?,?,?,?)');
            $stmt->execute([$first, $last, $bsa ?: null, $rank ?: null]);
            $message = 'Scout added successfully.';
        } else {
            $error = 'First and last name are required.';
        }
    }

    if ($action === 'deactivate') {
        $id = (int)($_POST['scout_id'] ?? 0);
        if ($id) {
            $pdo->prepare('UPDATE scouts SET active = 0 WHERE id = ?')->execute([$id]);
            $message = 'Scout deactivated.';
        }
    }

    if ($action === 'reactivate') {
        $id = (int)($_POST['scout_id'] ?? 0);
        if ($id) {
            $pdo->prepare('UPDATE scouts SET active = 1 WHERE id = ?')->execute([$id]);
            $message = 'Scout reactivated.';
        }
    }
}

$scouts = $pdo->query('SELECT * FROM scouts ORDER BY last_name, first_name')->fetchAll();
$ranks = ['Scout','Tenderfoot','Second Class','First Class','Star','Life','Eagle'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scouts — Trailhead</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; background: #f7f7f7; color: #222; }
        header { background: #0f766e; color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-left: 1rem; font-size: 0.95rem; }
        .wrap { max-width: 960px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; }
        h1, h2 { margin-top: 0; }
        label { display: block; margin: 0.75rem 0 0.3rem; font-weight: 600; font-size: 0.9rem; }
        input, select { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.95rem; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
        button { padding: 0.65rem 1.25rem; border: 0; border-radius: 6px; background: #0f766e; color: #fff; font-weight: 700; cursor: pointer; margin-top: 1rem; }
        button:hover { background: #0d5e57; }
        .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.85rem; margin-top: 0; }
        .btn-danger { background: #b00020; }
        .btn-danger:hover { background: #8a001a; }
        .btn-muted { background: #6b7280; }
        .btn-muted:hover { background: #4b5563; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 0.6rem 0.75rem; background: #f3f4f6; border-bottom: 2px solid #ddd; font-size: 0.85rem; }
        td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        tr.inactive td { color: #aaa; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-gray { background: #f3f4f6; color: #6b7280; }
        .msg { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .msg-success { background: #d1fae5; color: #065f46; }
        .msg-error { background: #fee2e2; color: #b00020; }
        .nav a { margin-right: 1rem; color: #0f766e; text-decoration: none; }
        .nav a:hover { text-decoration: underline; }
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
    <?php if ($message): ?><div class="msg msg-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card">
        <h2>Add Scout</h2>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <div class="row">
                <div>
                    <label>First Name *</label>
                    <input name="first_name" type="text" required>
                </div>
                <div>
                    <label>Last Name *</label>
                    <input name="last_name" type="text" required>
                </div>
            </div>
            <div class="row">
                <div>
                    <label>BSA Member ID</label>
                    <input name="bsa_id" type="text" placeholder="Optional">
                </div>
                <div>
                    <label>Current Rank</label>
                    <select name="rank">
                        <option value="">-- Select Rank --</option>
                        <?php foreach ($ranks as $r): ?>
                        <option value="<?= $r ?>"><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit">Add Scout</button>
        </form>
    </div>

    <div class="card">
        <h2>All Scouts (<?= count($scouts) ?>)</h2>
        <?php if (empty($scouts)): ?>
            <p style="color:#888">No scouts added yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>BSA ID</th>
                    <th>Rank</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($scouts as $s): ?>
                <tr class="<?= $s['active'] ? '' : 'inactive' ?>">
                    <td><?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name']) ?></td>
                    <td><?= htmlspecialchars($s['bsa_id'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($s['rank'] ?? '—') ?></td>
                    <td><span class="badge <?= $s['active'] ? 'badge-green' : 'badge-gray' ?>"><?= $s['active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="scout_id" value="<?= $s['id'] ?>">
                            <?php if ($s['active']): ?>
                                <input type="hidden" name="action" value="deactivate">
                                <button class="btn-sm btn-danger" onclick="return confirm('Deactivate this scout?')">Deactivate</button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="reactivate">
                                <button class="btn-sm btn-muted">Reactivate</button>
                            <?php endif; ?>
                        </form>
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
