<?php
// Trailhead — Run Callback Endpoint
// Receives structured results from Computer after a Scoutbook run
// POST https://trailhead.mjtroop911.org/api/run/callback.php

// Resolve app root: public/api/run -> public -> app root
$appRoot = dirname(__DIR__, 3);

// Load .env
$envFile = $appRoot . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Self-contained DB connection
try {
    $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
    $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
    $name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
    $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
    $pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed.']));
}

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed.']));
}

// Read raw input — works regardless of Content-Type header
$rawInput = file_get_contents('php://input');
if (empty($rawInput) && !empty($_POST)) {
    $rawInput = json_encode($_POST);
}

$body = json_decode($rawInput, true);
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
$runId   = isset($body['run_id']) ? (int) $body['run_id'] : 0;
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

    if (!in_array($type, ['rank', 'merit_badge', 'award'])) {
        $type = 'rank';
    }

    $statusMap = [
        'entered'          => 'entered',
        'success'          => 'entered',
        'already_approved' => 'already_approved',
        'needs_review'     => 'needs_review',
        'failed'           => 'failed',
        'error'            => 'failed',
    ];
    $status = $statusMap[strtolower($status)] ?? 'failed';

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

    if ($status === 'entered')      $succeeded++;
    elseif ($status === 'failed')   $failed++;
    else                            $needsReview++;
}

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

echo json_encode([
    'success'      => true,
    'run_id'       => $runId,
    'total'        => count($results),
    'succeeded'    => $succeeded,
    'failed'       => $failed,
    'needs_review' => $needsReview,
]);
