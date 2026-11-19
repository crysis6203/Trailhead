<?php

/**
 * This is a single-user app (just Chris, plus Cowork acting on his behalf),
 * so authentication is intentionally simple: one shared secret, checked on
 * every /api/* request. Anything more (sessions, user accounts) would be
 * complexity this app doesn't need.
 */
final class Auth
{
    public static function requireApiKey(): void
    {
        $expected = env('API_KEY');

        if (!$expected) {
            // Misconfiguration — fail closed, never fail open.
            http_response_code(500);
            echo json_encode(['error' => 'Server misconfigured: API_KEY not set']);
            exit;
        }

        $provided = self::extractProvidedKey();

        if (!$provided || !hash_equals($expected, $provided)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }

    private static function extractProvidedKey(): ?string
    {
        $header = $_SERVER['HTTP_X_API_KEY'] ?? null;
        if ($header) {
            return $header;
        }

        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return null;
    }
}
