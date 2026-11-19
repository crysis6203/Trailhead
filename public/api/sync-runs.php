<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (!empty($_GET['id'])) {
        handleGetOne($db, (int) $_GET['id']);
    } else {
        handleList($db);
    }
} elseif ($method === 'POST') {
    handlePost($db);
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

function handleGetOne(PDO $db, int $id): never
{
    $stmt = $db->prepare('SELECT * FROM sync_runs WHERE id = ?');
    $stmt->execute([$id]);
    $run = $stmt->fetch();

    if (!$run) {
        respond_error('Sync run not found', 404);
    }

    $itemsStmt = $db->prepare('
        SELECT * FROM sync_run_items WHERE sync_run_id = ? ORDER BY created_at ASC
    ');
    $itemsStmt->execute([$id]);
    $run['items'] = $itemsStmt->fetchAll();

    respond(['run' => $run]);
}

function handlePost(PDO $db): void
{
    $body = json_input();

    // Adding one or more items to an existing run — this is the common
    // case during a run, called immediately as each signoff/update happens
    // rather than batched at the end, so partial progress survives even if
    // the run fails partway through.
    if (!empty($body['id']) && (isset($body['item']) || isset($body['items']))) {
        handleAddItems($db, (int) $body['id'], $body);
        return;
    }

    // No id -> starting a new run.
    if (empty($body['id'])) {
        $stmt = $db->prepare('
            INSERT INTO sync_runs (run_type, scheduled_for, started_at, status)
            VALUES (?, ?, NOW(), NULL)
        ');
        $stmt->execute([$body['run_type'] ?? 'manual_both', $body['scheduled_for'] ?? null]);
        respond(['id' => (int) $db->lastInsertId()], 201);
    }

    // Has id, no item(s) -> completing the run (final counts + status).
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

function handleAddItems(PDO $db, int $runId, array $body): never
{
    $items = $body['items'] ?? [$body['item']];
    $allowedCategories = ['confirmed', 'flagged', 'roster_update', 'requirement_status_update'];

    $stmt = $db->prepare('
        INSERT INTO sync_run_items (sync_run_id, category, scout_id, scout_name, description, detail, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ');

    $count = 0;
    foreach ($items as $item) {
        if (!in_array($item['category'] ?? '', $allowedCategories, true)) {
            continue; // skip malformed entries rather than fail the whole batch
        }
        if (empty($item['description'])) {
            continue;
        }
        $stmt->execute([
            $runId,
            $item['category'],
            $item['scout_id'] ?? null,
            $item['scout_name'] ?? null,
            $item['description'],
            $item['detail'] ?? null,
        ]);
        $count++;
    }

    respond(['added' => $count]);
}
