<?php

declare(strict_types=1);

namespace Nanofin\Middleware;

use Nanofin\Models\SettingsModel;
use Nanofin\Models\UserModel;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * AuthMiddleware — protects routes that require a logged-in user.
 *
 * Behaviour:
 *  - In public mode (settings.public_mode = 1): passes through without check.
 *  - In private mode: requires an active session.
 *  - On every authenticated request: validates the session_token against the
 *    database to support global session revocation.
 *  - Updates last_activity on each authenticated request.
 *
 * Attach to the library route group (not to /admin — AdminMiddleware handles that).
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly SettingsModel            $settings,
        private readonly UserModel                $users,
    ) {}

    public function process(Request $request, Handler $handler): Response
    {
        session_start_safe();

        $publicMode = $this->settings->getBool('public_mode');

        // ── Case 1 : user is logged in ────────────────────────────
        if (!empty($_SESSION['user'])) {
            if (!$this->sessionIsValid()) {
                return $this->forceLogout($request);
            }

            // If the user must change their password, redirect to /first-login
            // (unless they are already on that page)
            if (!empty($_SESSION['user']['force_password_change'])) {
                if (!str_ends_with($request->getUri()->getPath(), '/first-login')) {
                    return $this->responseFactory->createResponse(302)
                        ->withHeader('Location', app_url('/first-login'));
                }
            }

            // Refresh last_activity periodically (every request is fine at
            // this scale — SQLite writes are fast)
            $this->users->updateLastActivity((int) $_SESSION['user']['id']);

            return $handler->handle($request);
        }

        // ── Case 2 : public mode — no login required ──────────────
        if ($publicMode) {
            return $handler->handle($request);
        }

        // ── Case 3 : private mode, no session — redirect to login ─
        return $this->redirectToLogin($request);
    }

    // ── Helpers ───────────────────────────────────────────────────

    /**
     * Verify the session token stored in $_SESSION matches the one in the DB.
     * If they differ, an admin has performed a global revocation.
     */
    private function sessionIsValid(): bool
    {
        $sessionData = $_SESSION['user'] ?? [];
        $userId      = (int) ($sessionData['id'] ?? 0);
        $token       = (string) ($sessionData['session_token'] ?? '');

        if ($userId === 0) {
            return false;
        }

        $user = $this->users->findById($userId);

        if ($user === null) {
            return false;
        }

        // null token in DB means the user explicitly logged out and the token
        // was cleared.  Any stored session referencing that user is invalid.
        if ($user['session_token'] === null) {
            return false;
        }

        return hash_equals($user['session_token'], $token);
    }

    private function forceLogout(Request $request): Response
    {
        $_SESSION = [];
        session_destroy();
        return $this->redirectToLogin($request);
    }

    private function redirectToLogin(Request $request): Response
    {
        $uri      = $request->getUri();
        $redirect = urlencode($uri->getPath());
        $home     = urlencode(app_url('/'));
        $location = app_url('/login') . ($redirect !== $home ? '?next=' . $redirect : '');

        return $this->responseFactory->createResponse(302)
            ->withHeader('Location', $location);
    }
}
