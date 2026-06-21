<?php
namespace App\Helpers;

use Psr\Http\Message\ResponseInterface as Response;

/**
 * Small helper so every route returns JSON the same way, with the
 * correct HTTP status code. Keeping this in one place means our
 * responses are consistent across all 20+ endpoints.
 */
class Json
{
    public static function write(Response $response, $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /** Shortcut for an error message + status code. */
    public static function error(Response $response, string $message, int $status = 400): Response
    {
        return self::write($response, ['error' => $message], $status);
    }
}
