<?php
// Queue controller

if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }

$pdo->exec("CREATE TABLE IF NOT EXISTS queue_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    scout_id INT NOT NULL,
    type ENUM('rank','merit_badge','award') NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    completed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$session_id = (int)($_GET['session_id'] ?? $_POST['session_id'] ?? 0);

if (!$session_id) {
    header('Location: /sessions');
    exit;
}

$session = $pdo->prepare('SELECT * FROM sessions WHERE id = ?');
$session->execute([$session_id]);
$session = $session->fetch();

if (!$session) { http_response_code(404); die('Session not found.'); }

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $scout_id  = (int)($_POST['scout_id'] ?? 0);
        $type      = $_POST['type'] ?? '';
        $item_name = trim($_POST['item_name'] ?? '');

        if ($scout_id && $type && $item_name) {
            $stmt = $pdo->prepare('INSERT INTO queue_items (session_id, scout_id, type, item_name) VALUES (?,?,?,?)');
            $stmt->execute([$session_id, $scout_id, $type, $item_name]);
            $message = 'Item added to queue.';
        } else {
            $error = 'All fields are required.';
        }
    }

    if ($action === 'remove') {
        $id = (int)($_POST['item_id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM queue_items WHERE id = ? AND session_id = ?')->execute([$id, $session_id]);
            $message = 'Item removed.';
        }
    }
}

// Scouts in this session
$scouts = $pdo->prepare('
    SELECT sc.* FROM scouts sc
    JOIN session_scouts ss ON ss.scout_id = sc.id
    WHERE ss.session_id = ?
    ORDER BY sc.last_name, sc.first_name
');
$scouts->execute([$session_id]);
$scouts = $scouts->fetchAll();

// Queue items
$items = $pdo->prepare('
    SELECT qi.*, sc.first_name, sc.last_name
    FROM queue_items qi
    JOIN scouts sc ON sc.id = qi.scout_id
    WHERE qi.session_id = ?
    ORDER BY sc.last_name, sc.first_name, qi.type, qi.item_name
');
$items->execute([$session_id]);
$items = $items->fetchAll();

$ranks = ['Scout','Tenderfoot','Second Class','First Class','Star','Life','Eagle'];
$merit_badges = ['Camping','Cooking','Citizenship in the Nation','Citizenship in the Community','Citizenship in the World','Communication','Emergency Preparedness','Environmental Science','First Aid','Personal Fitness','Personal Management','Swimming','Hiking','Cycling','Rowing','Archery','Art','Athletics','Aviation','Backpacking','Bird Study','Chess','Climbing','Collections','Crime Prevention','Disabilities Awareness','Dog Care','Electricity','Electronics','Engineering','Farm Mechanics','Fingerprinting','Fire Safety','Fish and Wildlife Management','Fishing','Forestry','Gardening','Genealogy','Geology','Golf','Graphic Arts','Home Repairs','Horsemanship','Indian Lore','Insect Study','Journalism','Kayaking','Landscape Architecture','Law','Leatherwork','Lifesaving','Mammal Study','Medicine','Metalwork','Mining in Society','Model Design and Building','Motorboating','Music','Nature','Nuclear Science','Oceanography','Orienteering','Painting','Photography','Pioneering','Plant Science','Plumbing','Pottery','Programming','Public Health','Public Speaking','Pulp and Paper','Radio','Railroading','Reading','Reptile and Amphibian Study','Rifle Shooting','Robotics','Salesmanship','Scholarship','Sculpture','Search and Rescue','Shotgun Shooting','Signs Signals and Codes','Skating','Small-Boat Sailing','Snow Sports','Soil and Water Conservation','Space Exploration','Sports','Stamp Collecting','Surveying','Sustainability','Theatre','Traffic Safety','Truck Transportation','Veterinary Medicine','Weather','Welding','Whitewater','Wilderness Survival','Wood Carving','Woodwork'];
sort($merit_badges);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue — <?= htmlspecialchars($session['name']) ?></title>
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
        .row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
        button { padding: 0.65rem 1.25rem; border: 0; border-radius: 6px; background: #0f766e; color: #fff; font-weight: 700; cursor: pointer; margin-top: 1rem; }
        button:hover { background: #0d5e57; }
        .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.85rem; margin-top: 0; }
        .btn-danger { background: #b00020; }
        .btn-danger:hover { background: #8a001a; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 0.6rem 0.75rem; background: #f3f4f6; border-bottom: 2px solid #ddd; font-size: 0.85rem; }
        td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #eee; font-size: 0.9rem; vertical-align: middle; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .badge-rank { background: #fef3c7; color: #92400e; }
        .badge-merit { background: #dbeafe; color: #1e40af; }
        .badge-award { background: #ede9fe; color: #5b21b6; }
        .msg { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .msg-success { background: #d1fae5; color: #065f46; }
        .msg-error { background: #fee2e2; color: #b00020; }
        a.btn-link { display: inline-block; padding: 0.5rem 1rem; background: #0f766e; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 600; }
        a.btn-link:hover { background: #0d5e57; }
        .session-meta { color: #666; font-size: 0.9rem; margin-bottom: 0; }
    </style>
    <script>
        function updateItemField() {
            const type = document.getElementById('type').value;
            const wrapper = document.getElementById('item_name_wrapper');
            const input = document.getElementById('item_name_input');
            const select = document.getElementById('item_name_select');
            if (type === 'merit_badge') {
                input.style.display = 'none';
                select.style.display = '';
                select.name = 'item_name';
                input.name = '';
            } else {
                input.style.display = '';
                select.style.display = 'none';
                input.name = 'item_name';
                select.name = '';
            }
        }
        document.addEventListener('DOMContentLoaded', updateItemField);
    </script>
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
        <h2><?= htmlspecialchars($session['name']) ?></h2>
        <p class="session-meta"><?= htmlspecialchars($session['session_date']) ?> &mdash; <?= count($scouts) ?> scouts &mdash; <?= count($items) ?> queued items</p>
    </div>

    <?php if ($session['status'] === 'open'): ?>
    <div class="card">
        <h2>Add to Queue</h2>
        <?php if (empty($scouts)): ?>
            <p style="color:#888">No scouts in this session. <a href="/sessions">Edit session &rarr;</a></p>
        <?php else: ?>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="session_id" value="<?= $session_id ?>">
            <div class="row3">
                <div>
                    <label>Scout</label>
                    <select name="scout_id" required>
                        <option value="">-- Select Scout --</option>
                        <?php foreach ($scouts as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Type</label>
                    <select name="type" id="type" onchange="updateItemField()" required>
                        <option value="rank">Rank Advancement</option>
                        <option value="merit_badge">Merit Badge</option>
                        <option value="award">Award</option>
                    </select>
                </div>
                <div id="item_name_wrapper">
                    <label>Item</label>
                    <select id="item_name_select" name="item_name" style="display:none">
                        <?php foreach ($merit_badges as $mb): ?>
                        <option value="<?= htmlspecialchars($mb) ?>"><?= htmlspecialchars($mb) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="item_name_input" name="item_name">
                        <option value="">-- Select Rank --</option>
                        <?php foreach ($ranks as $r): ?>
                        <option value="<?= $r ?>"><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit">Add to Queue</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>Queue (<?= count($items) ?>)</h2>
        <?php if (empty($items)): ?>
            <p style="color:#888">No items in queue yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Scout</th>
                    <th>Type</th>
                    <th>Item</th>
                    <?php if ($session['status'] === 'open'): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['last_name'] . ', ' . $item['first_name']) ?></td>
                    <td>
                        <span class="badge badge-<?= $item['type'] === 'rank' ? 'rank' : ($item['type'] === 'merit_badge' ? 'merit' : 'award') ?>">
                            <?= $item['type'] === 'merit_badge' ? 'Merit Badge' : ucfirst(str_replace('_', ' ', $item['type'])) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <?php if ($session['status'] === 'open'): ?>
                    <td>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                            <input type="hidden" name="session_id" value="<?= $session_id ?>">
                            <button class="btn-sm btn-danger" onclick="return confirm('Remove this item?')">Remove</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:1rem">
            <a class="btn-link" href="/run?session_id=<?= $session_id ?>">Run Advancements &rarr;</a>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
