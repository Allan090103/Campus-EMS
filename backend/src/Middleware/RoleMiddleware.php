<?php
namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use App\Helpers\Json;

/**
 * Role-based access control.
 *
 * Used together with AuthMiddleware. AuthMiddleware proves WHO you are;
 * RoleMiddleware checks whether your role is allowed on this route.
 * Example: new RoleMiddleware(['admin']) on /api/users blocks everyone
 * who is not an admin with 403 Forbidden.
 */
class RoleMiddleware
{
    private array $allowed;

    public function __construct(array $allowedRoles)
    {
        $this->allowed = $allowedRoles;
    }

    public function __invoke(Request $request, Handler $handler)
    {
        $user = $request->getAttribute('user');

        if (!$user || !in_array($user['role'], $this->allowed, true)) {
            $res = new \Slim\Psr7\Response();
            return Json::error($res, 'You do not have permission to access this resource.', 403);
        }

        return $handler->handle($request);
    }
}
