<?php
// Trailhead — Run Callback Endpoint
// Receives structured results from Computer after a Scoutbook run
// POST https://trailhead.mjtroop911.org/api/run/callback.php

header('Content-Type: application/json');

// Bootstrap: load env and PDO
require_once dirname(__DIR__, 3) . '/src/bootstrap.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed.']));
}

// Parse JSON body
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid JSON body.']));
}

// Validate shared secret
$expectedSecret = $_ENV['TRAILHEAD_SECRET'] ?? '';
$providedSecret = $body['secret'] ?? '';

if (empty($expectedSecret) || !hash_equals($expectedSecret, $providedSecret)) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized.']));
}

// Validate required fields
$runId  = isset($body['run_id']) ? (int) $body['run_id'] : 0;
$results = $body['results'] ?? [];

if ($runId <= 0 || empty($results) || !is_array($results)) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing run_id or results.']));
}

// Confirm run_history record exists
$stmt = $pdo->prepare('SELECT id FROM run_history WHERE id = ?');
$stmt->execute([$runId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    die(json_encode(['error' => 'Run history record not found.']));
}

// Counters
$succeeded   = 0;
$failed      = 0;
$needsReview = 0;

// Insert each requirement result into run_items
$insert = $pdo->prepare("
    INSERT INTO run_items
        (run_history_id, scout_name, type, item_name, requirement, completion_date, status, note)
    VALUES
        (:run_history_id, :scout_name, :type, :item_name, :requirement, :completion_date, :status, :note)
");

foreach ($results as $item) {
    $scout       = trim($item['scout']       ?? '');
    $type        = trim($item['type']        ?? 'rank');
    $itemName    = trim($item['item']        ?? '');
    $requirement = trim($item['requirement'] ?? '');
    $date        = trim($item['date']        ?? date('Y-m-d'));
    $status      = trim($item['status']      ?? 'failed');
    $note        = trim($item['note']        ?? '');

    // Normalise type value
    if (!in_array($type, ['rank', 'merit_badge', 'award'])) {
        $type = 'rank';
    }

    // Normalise status value
    $statusMap = [
        'entered'          => 'entered',
        'success'          => 'entered',
        'already_approved' => 'already_approved',
        'needs_review'     => 'needs_review',
        'failed'           => 'failed',
        'error'            => 'failed',
    ];
    $status = $statusMap[strtolower($status)] ?? 'failed';

    // Normalise date
    $parsedDate = date('Y-m-d', strtotime($date));
    if ($parsedDate === '1970-01-01') {
        $parsedDate = date('Y-m-d');
    }

    $insert->execute([
        ':run_history_id'  => $runId,
        ':scout_name'      => $scout,
        ':type'            => $type,
        ':item_name'       => $itemName,
        ':requirement'     => $requirement,
        ':completion_date' => $parsedDate,
        ':status'          => $status,
        ':note'            => $note ?: null,
    ]);

    // Tally counters
    if ($status === 'entered')      $succeeded++;
    elseif ($status === 'failed')   $failed++;
    else                            $needsReview++;
}

// Update run_history record with final counts and status
$pdo->prepare("
    UPDATE run_history SET
        status       = 'complete',
        completed_at = NOW(),
        total_items  = :total,
        succeeded    = :succeeded,
        failed       = :failed,
        needs_review = :needs_review
    WHERE id = :run_id
")->execute([
    ':total'        => count($results),
    ':succeeded'    => $succeeded,
    ':failed'       => $failed,
    ':needs_review' => $needsReview,
    ':run_id'       => $runId,
]);

// Return summary
echo json_encode([
    'success'      => true,
    'run_id'       => $runId,
    'total'        => count($results),
    'succeeded'    => $succeeded,
    'failed'       => $failed,
    'needs_review' => $needsReview,
]);
