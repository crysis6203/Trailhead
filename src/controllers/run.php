<?php
// Run controller — review and mark advancements complete

if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }

$session_id = (int)($_GET['session_id'] ?? $_POST['session_id'] ?? 0);

if (!$session_id) { header('Location: /sessions'); exit; }

$session = $pdo->prepare('SELECT * FROM sessions WHERE id = ?');
$session->execute([$session_id]);
$session = $session->fetch();

if (!$session) { http_response_code(404); die('Session not found.'); }

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_complete') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        if ($item_id) {
            $pdo->prepare('UPDATE queue_items SET completed = 1 WHERE id = ? AND session_id = ?')->execute([$item_id, $session_id]);
            $message = 'Marked as complete.';
        }
    }

    if ($action === 'mark_incomplete') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        if ($item_id) {
            $pdo->prepare('UPDATE queue_items SET completed = 0 WHERE id = ? AND session_id = ?')->execute([$item_id, $session_id]);
            $message = 'Marked as incomplete.';
        }
    }

    if ($action === 'mark_all_complete') {
        $pdo->prepare('UPDATE queue_items SET completed = 1 WHERE session_id = ?')->execute([$session_id]);
        $message = 'All items marked complete.';
    }
}

$items = $pdo->prepare('
    SELECT qi.*, sc.first_name, sc.last_name, sc.bsa_id
    FROM queue_items qi
    JOIN scouts sc ON sc.id = qi.scout_id
    WHERE qi.session_id = ?
    ORDER BY sc.last_name, sc.first_name, qi.type, qi.item_name
');
$items->execute([$session_id]);
$items = $items->fetchAll();

$total     = count($items);
$completed = count(array_filter($items, fn($i) => $i['completed']));
$pending   = $total - $completed;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Run — <?= htmlspecialchars($session['name']) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; background: #f7f7f7; color: #222; }
        header { background: #0f766e; color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-left: 1rem; font-size: 0.95rem; }
        .wrap { max-width: 960px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; }
        h1, h2 { margin-top: 0; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 0; }
        .stat { text-align: center; padding: 1rem; background: #f3f4f6; border-radius: 8px; }
        .stat-num { font-size: 2rem; font-weight: 700; color: #0f766e; }
        .stat-label { font-size: 0.85rem; color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 0.6rem 0.75rem; background: #f3f4f6; border-bottom: 2px solid #ddd; font-size: 0.85rem; }
        td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #eee; font-size: 0.9rem; vertical-align: middle; }
        tr.done td { color: #aaa; text-decoration: line-through; }
        tr.done td:last-child { text-decoration: none; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .badge-rank { background: #fef3c7; color: #92400e; }
        .badge-merit { background: #dbeafe; color: #1e40af; }
        .badge-award { background: #ede9fe; color: #5b21b6; }
        .badge-done { background: #d1fae5; color: #065f46; }
        button { padding: 0.65rem 1.25rem; border: 0; border-radius: 6px; background: #0f766e; color: #fff; font-weight: 700; cursor: pointer; }
        button:hover { background: #0d5e57; }
        .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.85rem; }
        .btn-muted { background: #6b7280; }
        .btn-muted:hover { background: #4b5563; }
        .msg { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; background: #d1fae5; color: #065f46; }
        .note { background: #fef9c3; border: 1px solid #fde68a; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; font-size: 0.95rem; color: #78350f; }
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
    <?php if ($message): ?><div class="msg"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="card">
        <h2><?= htmlspecialchars($session['name']) ?> &mdash; Run</h2>
        <div class="stats">
            <div class="stat"><div class="stat-num"><?= $total ?></div><div class="stat-label">Total Items</div></div>
            <div class="stat"><div class="stat-num"><?= $pending ?></div><div class="stat-label">Pending</div></div>
            <div class="stat"><div class="stat-num"><?= $completed ?></div><div class="stat-label">Completed</div></div>
        </div>
    </div>

    <div class="note">
        ⚠️ <strong>Manual entry required:</strong> Log into <a href="https://advancements.scouting.org" target="_blank">advancements.scouting.org</a> and enter each item below. Check them off here as you go to track your progress.
    </div>

    <?php if ($pending > 0): ?>
    <div style="margin-bottom:1rem">
        <form method="post" style="display:inline">
            <input type="hidden" name="action" value="mark_all_complete">
            <input type="hidden" name="session_id" value="<?= $session_id ?>">
            <button onclick="return confirm('Mark all items complete?')">Mark All Complete</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>Advancement Items</h2>
        <?php if (empty($items)): ?>
            <p style="color:#888">No items in queue. <a href="/queue?session_id=<?= $session_id ?>">Add items &rarr;</a></p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Scout</th>
                    <th>BSA ID</th>
                    <th>Type</th>
                    <th>Item</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr class="<?= $item['completed'] ? 'done' : '' ?>">
                    <td><?= htmlspecialchars($item['last_name'] . ', ' . $item['first_name']) ?></td>
                    <td><?= htmlspecialchars($item['bsa_id'] ?? '—') ?></td>
                    <td>
                        <span class="badge badge-<?= $item['completed'] ? 'done' : ($item['type'] === 'rank' ? 'rank' : ($item['type'] === 'merit_badge' ? 'merit' : 'award')) ?>">
                            <?= $item['completed'] ? 'Done' : ($item['type'] === 'merit_badge' ? 'Merit Badge' : ucfirst(str_replace('_', ' ', $item['type']))) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td><?= $item['completed'] ? '&#10003;' : '&mdash;' ?></td>
                    <td>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="session_id" value="<?= $session_id ?>">
                            <?php if (!$item['completed']): ?>
                                <input type="hidden" name="action" value="mark_complete">
                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                <button class="btn-sm">Mark Done</button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="mark_incomplete">
                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                <button class="btn-sm btn-muted">Undo</button>
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
