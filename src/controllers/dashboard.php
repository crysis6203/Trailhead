<?php
// Dashboard controller
if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }

// Queue count (run_history based)
$runCount = 0;
try {
    $q = $pdo->prepare('SELECT COUNT(*) FROM run_history WHERE user_id = ?');
    $q->execute([$_SESSION['user_id']]);
    $runCount = (int)$q->fetchColumn();
} catch (Exception $e) {}

// Recent run history (last 5) with new columns
$runs = [];
try {
    $r = $pdo->prepare('
        SELECT id, session_name, summary, created_at, status, succeeded, failed, needs_review
        FROM run_history
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ');
    $r->execute([$_SESSION['user_id']]);
    $runs = $r->fetchAll();
} catch (Exception $e) {}

// Recent sessions (last 5)
$sessions = [];
try {
    $s = $pdo->prepare('SELECT id, name, created_at FROM sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
    $s->execute([$_SESSION['user_id']]);
    $sessions = $s->fetchAll();
} catch (Exception $e) {}
?>