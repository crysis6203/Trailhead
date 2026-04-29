<?php
// Meeting Mode — Trailhead
if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }

$pdo->exec("CREATE TABLE IF NOT EXISTS run_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_name VARCHAR(200) DEFAULT NULL,
    raw_notes TEXT,
    prompt TEXT,
    raw_results TEXT,
    summary VARCHAR(200) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS queue_state (
    user_id INT NOT NULL PRIMARY KEY,
    queue_json MEDIUMTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_run') {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare('INSERT INTO run_history (user_id, session_name, raw_notes, prompt, raw_results, summary) VALUES (?,?,?,?,?,?)');
    $stmt->execute([
        $_SESSION['user_id'],
        trim($_POST['session_name'] ?? ''),
        $_POST['raw_notes'] ?? '',
        $_POST['prompt'] ?? '',
        $_POST['raw_results'] ?? '',
        $_POST['summary'] ?? '',
    ]);
    // Auto-clear the queue after saving
    $pdo->prepare('INSERT INTO queue_state (user_id, queue_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE queue_json = VALUES(queue_json), updated_at = CURRENT_TIMESTAMP')
        ->execute([$_SESSION['user_id'], '[]']);
    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_queue') {
    header('Content-Type: application/json');
    $json = $_POST['queue_json'] ?? '[]';
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) { echo json_encode(['ok' => false, 'error' => 'invalid json']); exit; }
    $pdo->prepare('INSERT INTO queue_state (user_id, queue_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE queue_json = VALUES(queue_json), updated_at = CURRENT_TIMESTAMP')
        ->execute([$_SESSION['user_id'], $json]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'load_queue') {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare('SELECT queue_json FROM queue_state WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    echo json_encode(['ok' => true, 'queue' => $row ? json_decode($row['queue_json'], true) : []]);
    exit;
}

// Fetch run history with new columns
$histStmt = $pdo->prepare('
    SELECT id, session_name, summary, created_at, status, succeeded, failed, needs_review, total_items
    FROM run_history
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 20
');
$histStmt->execute([$_SESSION['user_id']]);
$history = $histStmt->fetchAll();

// Fetch all run_items for those runs in one query
$runItems = [];
if (!empty($history)) {
    $ids = implode(',', array_map('intval', array_column($history, 'id')));
    $itemStmt = $pdo->query("
        SELECT run_history_id, scout_name, type, item_name, requirement, completion_date, status, note
        FROM run_items
        WHERE run_history_id IN ({$ids})
        ORDER BY id ASC
    ");
    foreach ($itemStmt->fetchAll() as $item) {
        $runItems[$item['run_history_id']][] = $item;
    }
}
?>