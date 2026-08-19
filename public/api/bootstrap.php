<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak errors to output; log instead

require __DIR__ . '/../../src/Database.php';
require __DIR__ . '/../../src/Auth.php';

// --- Load .env if present (simple, no composer dependency) -----------------
$envPath = __DIR__ . '/../../.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

header('Content-Type: application/json; charset=utf-8');

// Every endpoint requires the API key — including calls from Chris's own
// frontend, which stores the key in localStorage after first entry.
Auth::requireApiKey();

$db = Database::connection();

function json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function respond_error(string $message, int $status = 400): never
{
    respond(['error' => $message], $status);
}
