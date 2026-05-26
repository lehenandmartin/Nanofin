<?php

declare(strict_types=1);

namespace Nanofin\Middleware;

use Nanofin\Models\UserModel;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Exception\HttpForbiddenException;

/**
 * AdminMiddleware — restricts a route group to authenticated admins.
 *
 * Always requires a login (regardless of public_mode) and checks that the
 * authenticated user has role = 'admin'.
 *
 * Also performs session-token validation (same revocation check as
 * AuthMiddleware) so the admin panel is always consistent.
 */
final class AdminMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly UserModel                $users,
    ) {}

    public function process(Request $request, Handler $handler): Response
    {
        session_start_safe();

        // No session at all → login
        if (empty($_SESSION['user'])) {
            return $this->redirectToLogin($request);
        }

        // Validate session token (revocation support)
        if (!$this->sessionIsValid()) {
            $_SESSION = [];
            session_destroy();
            return $this->redirectToLogin($request);
        }

        // Must be admin
        if (($_SESSION['user']['role'] ?? '') !== 'admin') {
            throw new HttpForbiddenException($request);
        }

        // If the admin must change their password, redirect to /first-login
        if (!empty($_SESSION['user']['force_password_change'])) {
            return $this->responseFactory->createResponse(302)
                ->withHeader('Location', app_url('/first-login'));
        }

        // Refresh last_activity
        $this->users->updateLastActivity((int) $_SESSION['user']['id']);

        return $handler->handle($request);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function sessionIsValid(): bool
    {
        $sessionData = $_SESSION['user'] ?? [];
        $userId      = (int) ($sessionData['id'] ?? 0);
        $token       = (string) ($sessionData['session_token'] ?? '');

        if ($userId === 0) {
            return false;
        }

        $user = $this->users->findById($userId);

        if ($user === null || $user['session_token'] === null) {
            return false;
        }

        return hash_equals($user['session_token'], $token);
    }

    private function redirectToLogin(Request $request): Response
    {
        $uri      = $request->getUri();
        $redirect = urlencode($uri->getPath());

        return $this->responseFactory->createResponse(302)
            ->withHeader('Location', app_url('/login') . '?next=' . $redirect);
    }
}
