<?php

declare(strict_types=1);

namespace Nanofin\Middleware;

use Nanofin\Models\SessionModel;
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
 *  - On every authenticated request: validates the session token against the
 *    sessions table and checks for expiry (session_max_days setting).
 *  - Updates last_activity on each authenticated request (throttled to 1/min).
 *
 * Attach to the library route group (not to /admin — AdminMiddleware handles that).
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly SettingsModel            $settings,
        private readonly UserModel                $users,
        private readonly SessionModel             $sessions,
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
     * Verify that the session token in $_SESSION corresponds to a valid,
     * non-expired row in the sessions table.
     */
    private function sessionIsValid(): bool
    {
        $sessionData = $_SESSION['user'] ?? [];
        $userId      = (int) ($sessionData['id'] ?? 0);
        $token       = (string) ($sessionData['session_token'] ?? '');

        if ($userId === 0 || $token === '') {
            return false;
        }

        $session = $this->sessions->findByToken($token);

        if ($session === null || (int) $session['user_id'] !== $userId) {
            return false;
        }

        // Check session expiry against session_max_days setting
        $maxDays = (int) $this->settings->get('session_max_days', '30');
        if ($maxDays > 0) {
            $expires = strtotime($session['created_at']) + ($maxDays * 86400);
            if ($expires < time()) {
                $this->sessions->deleteById((int) $session['id']);
                return false;
            }
        }

        // Throttle last_activity update to once per minute
        $now         = time();
        $lastUpdated = (int) ($_SESSION['session_updated_at'] ?? 0);
        if ($now - $lastUpdated >= 60) {
            $this->sessions->updateLastActivity((int) $session['id']);
            $_SESSION['session_updated_at'] = $now;
        }

        return true;
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
