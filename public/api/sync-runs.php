<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handleList($db);
} elseif ($method === 'POST') {
    handleCreateOrUpdate($db);
} else {
    respond_error('Method not allowed', 405);
}

function handleList(PDO $db): never
{
    $limit = min((int) ($_GET['limit'] ?? 20), 100);
    $stmt = $db->prepare('SELECT * FROM sync_runs ORDER BY started_at DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    respond(['runs' => $stmt->fetchAll()]);
}

function handleCreateOrUpdate(PDO $db): never
{
    $body = json_input();

    // No id -> starting a new run. Cowork calls this first, at pre-flight,
    // then PATCHes-via-POST the same id again once the run finishes.
    if (empty($body['id'])) {
        $stmt = $db->prepare('
            INSERT INTO sync_runs (run_type, scheduled_for, started_at, status)
            VALUES (?, ?, NOW(), NULL)
        ');
        $stmt->execute([$body['run_type'] ?? 'manual_both', $body['scheduled_for'] ?? null]);
        respond(['id' => (int) $db->lastInsertId()], 201);
    }

    $stmt = $db->prepare('
        UPDATE sync_runs SET
            completed_at = NOW(),
            push_confirmed_count = ?,
            push_flagged_count = ?,
            pull_updated_count = ?,
            status = ?,
            fail_reason = ?
        WHERE id = ?
    ');
    $stmt->execute([
        $body['push_confirmed_count'] ?? 0,
        $body['push_flagged_count'] ?? 0,
        $body['pull_updated_count'] ?? 0,
        $body['status'] ?? 'success',
        $body['fail_reason'] ?? null,
        $body['id'],
    ]);

    if ($stmt->rowCount() === 0) {
        respond_error('Sync run not found', 404);
    }

    respond(['id' => (int) $body['id'], 'updated' => true]);
}
