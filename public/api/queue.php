<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handleList($db);
} elseif ($method === 'POST') {
    handleCreate($db);
} elseif ($method === 'DELETE') {
    handleDelete($db);
} else {
    respond_error('Method not allowed', 405);
}

function handleList(PDO $db): never
{
    $rows = $db->query('
        SELECT s.id AS signoff_id, s.scout_id, sc.name AS scout_name, sc.patrol,
               s.requirement_type, s.comment, s.completion_date, s.noted_at,
               COALESCE(rr.requirement_code, mbr.requirement_code) AS req_code,
               COALESCE(rr.display_text, mbr.display_text) AS req_text
        FROM signoffs s
        JOIN scouts sc ON sc.id = s.scout_id
        LEFT JOIN rank_requirements rr ON rr.id = s.rank_requirement_id
        LEFT JOIN merit_badge_requirements mbr ON mbr.id = s.merit_badge_requirement_id
        WHERE s.status = "pending"
        ORDER BY sc.name, s.noted_at
    ')->fetchAll();

    // Group by scout for the review UI (see /areas/trailhead: "wants the
    // queue/review screen displayed grouped by scout, not by requirement").
    $byScout = [];
    foreach ($rows as $row) {
        $byScout[$row['scout_id']] ??= [
            'name' => $row['scout_name'],
            'patrol' => $row['patrol'],
            'items' => [],
        ];
        $byScout[$row['scout_id']]['items'][] = [
            'signoffId' => (int) $row['signoff_id'],
            'type' => $row['requirement_type'],
            'code' => $row['req_code'],
            'text' => $row['req_text'],
            'comment' => $row['comment'],
            'completionDate' => $row['completion_date'],
            'notedAt' => $row['noted_at'],
        ];
    }

    respond(['scouts' => array_values($byScout)]);
}

function handleCreate(PDO $db): never
{
    $body = json_input();

    $required = ['scout_id', 'requirement_type', 'completion_date'];
    foreach ($required as $field) {
        if (!isset($body[$field])) {
            respond_error("Missing field: {$field}", 422);
        }
    }

    if (!in_array($body['requirement_type'], ['rank', 'merit_badge', 'award', 'leadership'], true)) {
        respond_error('Invalid requirement_type', 422);
    }

    $rankReqId = $body['rank_requirement_id'] ?? null;
    $mbReqId = $body['merit_badge_requirement_id'] ?? null;

    if ($body['requirement_type'] === 'rank' && !$rankReqId) {
        respond_error('rank_requirement_id required for requirement_type=rank', 422);
    }
    if ($body['requirement_type'] === 'merit_badge' && !$mbReqId) {
        respond_error('merit_badge_requirement_id required for requirement_type=merit_badge', 422);
    }

    $comment = isset($body['comment']) ? substr((string) $body['comment'], 0, 150) : null;

    $stmt = $db->prepare('
        INSERT INTO signoffs
            (scout_id, requirement_type, rank_requirement_id, merit_badge_requirement_id,
             comment, completion_date, noted_at, status)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), "pending")
    ');
    $stmt->execute([
        $body['scout_id'],
        $body['requirement_type'],
        $rankReqId,
        $mbReqId,
        $comment,
        $body['completion_date'],
    ]);

    respond(['id' => (int) $db->lastInsertId()], 201);
}

function handleDelete(PDO $db): never
{
    $id = $_GET['id'] ?? null;
    if (!$id) {
        respond_error('Missing id parameter', 422);
    }

    // Only pending items can be removed this way — anything already pushed
    // is Scoutbook's problem now, not the queue's (matches: "Removing an
    // item from the queue should delete outright, no undo window").
    $stmt = $db->prepare('DELETE FROM signoffs WHERE id = ? AND status = "pending"');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        respond_error('Signoff not found or not pending', 404);
    }

    respond(['deleted' => true]);
}
