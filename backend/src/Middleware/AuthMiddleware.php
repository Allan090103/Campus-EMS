<?php
namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use App\Helpers\Token;
use App\Helpers\Json;

/**
 * Authentication middleware.
 *
 * Runs before any PROTECTED route. It looks for a Bearer token in the
 * Authorization header, verifies the signature/expiry, and attaches the
 * decoded user payload to the request as the "user" attribute so the
 * route handler can read who is calling. If the token is missing or
 * invalid it short-circuits with 401 Unauthorized.
 */
class AuthMiddleware
{
    private array $cfg;

    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
    }

    public function __invoke(Request $request, Handler $handler)
    {
        $header = $request->getHeaderLine('Authorization');

        if (!preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            $res = new \Slim\Psr7\Response();
            return Json::error($res, 'Missing or malformed Authorization header.', 401);
        }

        try {
            $payload = Token::decode($m[1], $this->cfg);
        } catch (\Throwable $e) {
            $res = new \Slim\Psr7\Response();
            return Json::error($res, 'Invalid or expired token.', 401);
        }

        // Make the authenticated user available to route handlers.
        $request = $request->withAttribute('user', $payload);
        return $handler->handle($request);
    }
}
