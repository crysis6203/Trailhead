<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak errors to output; log instead

require __DIR__ . '/../../src/env.php';
require __DIR__ . '/../../src/Database.php';
require __DIR__ . '/../../src/Auth.php';

// --- Load .env if present (simple, no composer dependency) -----------------
// Checked in order:
//   1. Cloudways' private_html folder — a sibling of public_html, one level
//      above the webroot, provided specifically for sensitive config files
//      like this one. This is where .env lives in production.
//   2. The old public_html-relative location, for local development where
//      there's no private_html (e.g. `php -S` from public/, repo root .env).
$envCandidates = [
    __DIR__ . '/../../../private_html/.env',
    __DIR__ . '/../../.env',
];

foreach ($envCandidates as $envPath) {
    if (!file_exists($envPath)) {
        continue;
    }
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        // NOT putenv(): some managed hosts (Cloudways included) disable it
        // in php.ini, since it mutates the whole PHP-FPM worker process
        // rather than just the current request. A plain $_ENV array write
        // is never disabled and works identically for our purposes — see
        // src/env.php for the read side.
        $_ENV[trim($key)] = trim($value);
    }
    break; // first match wins
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
