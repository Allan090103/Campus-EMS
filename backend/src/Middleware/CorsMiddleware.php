<?php
namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * CORS middleware.
 *
 * The Vue app runs on a different origin (http://localhost:5173) from the
 * API (http://localhost:8000), so the browser blocks requests unless the
 * API explicitly allows that origin. This adds the required headers and
 * answers the browser's pre-flight OPTIONS request.
 */
class CorsMiddleware
{
    public function __invoke(Request $request, Handler $handler)
    {
        // Pre-flight request: respond immediately with the CORS headers.
        if ($request->getMethod() === 'OPTIONS') {
            $response = new \Slim\Psr7\Response();
            return $this->withHeaders($response);
        }

        $response = $handler->handle($request);
        return $this->withHeaders($response);
    }

    private function withHeaders($response)
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    }
}
