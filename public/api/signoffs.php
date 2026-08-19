<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    respond_error('Method not allowed', 405);
}

$id = $_GET['id'] ?? null;
if (!$id) {
    respond_error('Missing id parameter', 422);
}

$body = json_input();
$status = $body['status'] ?? null;

$allowed = ['pushed', 'confirmed', 'flagged', 'pending']; // 'pending' = re-queue
if (!in_array($status, $allowed, true)) {
    respond_error('status must be one of: ' . implode(', ', $allowed), 422);
}

if ($status === 'confirmed') {
    $stmt = $db->prepare('UPDATE signoffs SET status = "confirmed", confirmed_at = NOW(), flag_reason = NULL WHERE id = ?');
    $stmt->execute([$id]);
} elseif ($status === 'flagged') {
    $reason = $body['flag_reason'] ?? 'No reason provided';
    $stmt = $db->prepare('UPDATE signoffs SET status = "flagged", flag_reason = ? WHERE id = ?');
    $stmt->execute([$reason, $id]);
} elseif ($status === 'pushed') {
    $stmt = $db->prepare('UPDATE signoffs SET status = "pushed", pushed_at = NOW() WHERE id = ?');
    $stmt->execute([$id]);
} else { // 'pending' — re-queue, per Chris: "Re-queuing puts this back in the active queue for the next sync"
    $stmt = $db->prepare('UPDATE signoffs SET status = "pending", flag_reason = NULL, pushed_at = NULL WHERE id = ?');
    $stmt->execute([$id]);
}

if ($stmt->rowCount() === 0) {
    respond_error('Signoff not found', 404);
}

respond(['id' => (int) $id, 'status' => $status]);
