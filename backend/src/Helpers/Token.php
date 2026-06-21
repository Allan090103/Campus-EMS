<?php
namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Wraps the firebase/php-jwt library so the rest of the app deals with
 * simple create()/decode() calls. The token payload carries the user's
 * id, role and name so protected routes know who is calling without
 * another database hit.
 */
class Token
{
    /** Build and sign a JWT for a logged-in user. */
    public static function create(array $user, array $cfg): string
    {
        $now = time();
        $payload = [
            'iat' => $now,                        // issued at
            'exp' => $now + $cfg['jwt_ttl'],      // expiry
            'sub' => (int) $user['id'],           // subject = user id
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];
        return JWT::encode($payload, $cfg['jwt_secret'], $cfg['jwt_alg']);
    }

    /** Decode + verify a token. Throws if invalid/expired. Returns array payload. */
    public static function decode(string $jwt, array $cfg): array
    {
        $decoded = JWT::decode($jwt, new Key($cfg['jwt_secret'], $cfg['jwt_alg']));
        return (array) $decoded;
    }
}
