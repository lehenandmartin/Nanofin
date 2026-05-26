<?php

declare(strict_types=1);

namespace Nanofin\Controllers;

use Nanofin\Core\NotificationService;
use Nanofin\Core\Translator;
use Nanofin\Models\AuthTokenModel;
use Nanofin\Models\LoginAttemptModel;
use Nanofin\Models\UserModel;
use Nanofin\Models\SettingsModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class AuthController
{
    /** Maximum failed logins per IP within the rate-limit window. */
    private const MAX_ATTEMPTS = 5;
    /** Window length in seconds (15 minutes). */
    private const RATE_WINDOW  = 900;

    public function __construct(
        private readonly Twig                $twig,
        private readonly UserModel           $users,
        private readonly SettingsModel       $settings,
        private readonly Translator          $translator,
        private readonly LoginAttemptModel   $attempts,
        private readonly NotificationService $notifications,
        private readonly AuthTokenModel      $authTokens,
    ) {}

    // ── GET /login ────────────────────────────────────────────────

    public function showLogin(Request $request, Response $response): Response
    {
        // Already logged in → redirect home
        if (!empty($_SESSION['user'])) {
            return $response->withHeader('Location', app_url('/'))->withStatus(302);
        }

        $next = $this->sanitizeNext($request->getQueryParams()['next'] ?? '');

        return $this->twig->render($response, 'auth/login.twig', [
            'next'                  => $next,
            'forgotPasswordEnabled' => $this->forgotPasswordEnabled(),
            'magicLinkEnabled'      => $this->magicLinkEnabled(),
        ]);
    }

    // ── POST /login ───────────────────────────────────────────────

    public function login(Request $request, Response $response): Response
    {
        $body       = (array) $request->getParsedBody();
        $identifier = trim((string) ($body['identifier'] ?? ''));
        $password   = (string) ($body['password']    ?? '');
        $csrf       = (string) ($body['csrf_token']  ?? '');
        $next       = $this->sanitizeNext((string) ($body['next'] ?? ''));
        $ip         = $this->clientIp($request);

        // CSRF check
        if (!csrf_verify($csrf)) {
            return $this->renderLogin($response, $this->translator->trans('auth.login.invalid'), $next, $identifier);
        }

        // ── Rate-limit check ────────────────────────────────────────
        if ($this->attempts->countRecent($ip, self::RATE_WINDOW) >= self::MAX_ATTEMPTS) {
            return $this->renderLogin(
                $response,
                $this->translator->trans('auth.login.too_many_attempts'),
                $next,
                $identifier,
            );
        }

        // Presence check
        if ($identifier === '' || $password === '') {
            return $this->renderLogin($response, $this->translator->trans('auth.login.required'), $next, $identifier);
        }

        // Look up by username first, then by email if the identifier contains '@'
        $user = $this->users->findByUsername($identifier);
        if ($user === null && str_contains($identifier, '@')) {
            $user = $this->users->findByEmail($identifier);
        }

        if ($user === null || !password_verify($password, $user['password'])) {
            // Constant-time-ish: still run verify to resist timing attacks
            // even when no user was found
            if ($user === null) {
                password_verify($password, '$2y$12$invalid.hash.padding.to.keep.timing');
            }
            // Record failed attempt
            $this->attempts->record($ip);
            return $this->renderLogin($response, $this->translator->trans('auth.login.invalid'), $next, $identifier);
        }

        // ── Successful login ────────────────────────────────────────

        // Clear brute-force counter and purge old records
        $this->attempts->clearForIp($ip);
        $this->attempts->purgeOld();

        // Generate a fresh session token and persist it
        $sessionToken = bin2hex(random_bytes(16));
        $this->users->setSessionToken($user['id'], $sessionToken);

        // Rotate session ID to prevent fixation
        session_regenerate_id(true);

        // Store user data in session
        $_SESSION['user'] = [
            'id'                    => $user['id'],
            'username'              => $user['username'],
            'role'                  => $user['role'],
            'content_access'        => $user['content_access'],
            'session_token'         => $sessionToken,
            'force_password_change' => (bool) ($user['force_password_change'] ?? false),
        ];

        // Apply the user's preferred locale if set
        $locale = $this->settings->get('default_locale', 'en');
        $_SESSION['locale'] = $locale;
        $this->translator->setLocale($locale);

        // Update last_activity
        $this->users->updateLastActivity($user['id']);

        // Redirect: honour ?next= if set, otherwise always go to home
        $redirect = $next !== '' ? $next : app_url('/');

        return $response->withHeader('Location', $redirect)->withStatus(302);
    }

    // ── POST /login/identify ─────────────────────────────────────

    /**
     * AJAX endpoint — Phase 1 of the login flow.
     * Receives an identifier (email or username), determines the next phase,
     * and optionally sends a magic-link email.
     * Always responds with JSON: {phase: 'magic_sent'|'password'} or {error: '...'}
     */
    public function identify(Request $request, Response $response): Response
    {
        $body       = (array) $request->getParsedBody();
        $identifier = trim((string) ($body['identifier'] ?? ''));
        $csrf       = (string) ($body['csrf_token']  ?? '');
        $ip         = $this->clientIp($request);

        if (!csrf_verify($csrf)) {
            return $this->jsonResponse($response, ['error' => $this->translator->trans('auth.login.invalid')]);
        }

        if ($this->attempts->countRecent($ip, self::RATE_WINDOW) >= self::MAX_ATTEMPTS) {
            return $this->jsonResponse($response, ['error' => $this->translator->trans('auth.login.too_many_attempts')]);
        }

        // Record attempt against spam (regardless of outcome)
        $this->attempts->record($ip);

        if ($identifier === '') {
            return $this->jsonResponse($response, ['error' => $this->translator->trans('auth.login.required')]);
        }

        $isEmail = str_contains($identifier, '@');

        if ($isEmail && $this->magicLinkEnabled()) {
            // Find user by email — silently skip if not found (no enumeration)
            $user = $this->users->findByEmail($identifier);
            if ($user !== null) {
                $rawToken = $this->authTokens->generate((int) $user['id']);
                $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $base     = rtrim($_ENV['APP_BASE_PATH'] ?? '', '/');
                $magicUrl = $scheme . '://' . $host . $base . '/auth/magic/' . $rawToken;
                $this->notifications->sendMagicLink($user['email'], $user['username'], $magicUrl);
            }
            return $this->jsonResponse($response, ['phase' => 'magic_sent']);
        }

        return $this->jsonResponse($response, ['phase' => 'password']);
    }

    // ── GET /auth/magic/{token} ───────────────────────────────────

    public function magicLogin(Request $request, Response $response, array $args): Response
    {
        $rawToken = preg_replace('/[^a-f0-9]/', '', (string) ($args['token'] ?? ''));

        $row = $this->authTokens->findValid($rawToken);

        if ($row === null) {
            flash('error', $this->translator->trans('auth.magic_link.expired'));
            return $response->withHeader('Location', app_url('/login'))->withStatus(302);
        }

        // Consume the token (single-use)
        $this->authTokens->consume((int) $row['id']);
        $this->authTokens->purgeExpired();

        $user = $this->users->findById((int) $row['user_id']);
        if ($user === null) {
            flash('error', $this->translator->trans('auth.magic_link.expired'));
            return $response->withHeader('Location', app_url('/login'))->withStatus(302);
        }

        // ── Open session (same as password login) ──────────────────
        $this->attempts->clearForIp($this->clientIp($request));

        $sessionToken = bin2hex(random_bytes(16));
        $this->users->setSessionToken($user['id'], $sessionToken);

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'                    => $user['id'],
            'username'              => $user['username'],
            'role'                  => $user['role'],
            'content_access'        => $user['content_access'],
            'session_token'         => $sessionToken,
            'force_password_change' => (bool) ($user['force_password_change'] ?? false),
        ];

        $locale = $this->settings->get('default_locale', 'en');
        $_SESSION['locale'] = $locale;
        $this->translator->setLocale($locale);

        $this->users->updateLastActivity($user['id']);

        $redirect = !empty($_SESSION['user']['force_password_change'])
            ? app_url('/first-login')
            : app_url('/');

        return $response->withHeader('Location', $redirect)->withStatus(302);
    }

    // ── GET /first-login ──────────────────────────────────────────

    public function showFirstLogin(Request $request, Response $response): Response
    {
        if (empty($_SESSION['user'])) {
            return $response->withHeader('Location', app_url('/login'))->withStatus(302);
        }

        if (empty($_SESSION['user']['force_password_change'])) {
            return $response->withHeader('Location', app_url('/'))->withStatus(302);
        }

        return $this->twig->render($response, 'auth/first_login.twig');
    }

    // ── POST /first-login ─────────────────────────────────────────

    public function firstLogin(Request $request, Response $response): Response
    {
        if (empty($_SESSION['user']) || empty($_SESSION['user']['force_password_change'])) {
            return $response->withHeader('Location', app_url('/'))->withStatus(302);
        }

        $body    = (array) $request->getParsedBody();
        $csrf    = (string) ($body['csrf_token']      ?? '');
        $new     = (string) ($body['new_password']     ?? '');
        $confirm = (string) ($body['confirm_password'] ?? '');

        if (!csrf_verify($csrf)) {
            return $response->withHeader('Location', app_url('/first-login'))->withStatus(302);
        }

        if (mb_strlen($new) < 8) {
            return $this->twig->render($response, 'auth/first_login.twig', [
                'error' => $this->translator->trans('profile.password.too_short'),
            ]);
        }

        if ($new !== $confirm) {
            return $this->twig->render($response, 'auth/first_login.twig', [
                'error' => $this->translator->trans('profile.password.mismatch'),
            ]);
        }

        $userId = (int) $_SESSION['user']['id'];
        $this->users->updatePassword($userId, password_hash($new, PASSWORD_BCRYPT));
        $this->users->setForcePasswordChange($userId, false);

        // Clear the flag in the current session
        $_SESSION['user']['force_password_change'] = false;

        return $response->withHeader('Location', app_url('/'))->withStatus(302);
    }

    // ── GET /forgot-password ──────────────────────────────────────

    public function showForgotPassword(Request $request, Response $response): Response
    {
        if (!$this->forgotPasswordEnabled()) {
            return $response->withHeader('Location', app_url('/login'))->withStatus(302);
        }

        $params  = $request->getQueryParams();
        $success = isset($params['sent']);

        return $this->twig->render($response, 'auth/forgot_password.twig', [
            'success' => $success,
        ]);
    }

    // ── POST /forgot-password ─────────────────────────────────────

    public function forgotPassword(Request $request, Response $response): Response
    {
        if (!$this->forgotPasswordEnabled()) {
            return $response->withHeader('Location', app_url('/login'))->withStatus(302);
        }

        $body  = (array) $request->getParsedBody();
        $csrf  = (string) ($body['csrf_token'] ?? '');
        $email = trim((string) ($body['email'] ?? ''));
        $ip    = $this->clientIp($request);

        // CSRF check
        if (!csrf_verify($csrf)) {
            return $response->withHeader('Location', app_url('/forgot-password'))->withStatus(302);
        }

        // Rate-limit (shared with the login form)
        if ($this->attempts->countRecent($ip, self::RATE_WINDOW) >= self::MAX_ATTEMPTS) {
            return $response->withHeader('Location', app_url('/forgot-password'))->withStatus(302);
        }

        // Record attempt to prevent spam (regardless of email validity)
        $this->attempts->record($ip);

        // Look up user — silently do nothing if not found or no email on file
        if ($email !== '') {
            $user = $this->users->findByEmail($email);
            if ($user !== null && ($user['email'] ?? '') !== '') {
                // Generate a 12-character temporary password
                $chars    = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                $password = '';
                for ($i = 0; $i < 12; $i++) {
                    $password .= $chars[random_int(0, strlen($chars) - 1)];
                }

                // Save hashed password, invalidate active session, and force change on next login
                $this->users->updatePassword($user['id'], password_hash($password, PASSWORD_BCRYPT));
                $this->users->setSessionToken($user['id'], null);
                $this->users->setForcePasswordChange($user['id'], true);

                // Send email (non-fatal if SMTP fails)
                $siteUrl = (string) $request->getUri()->withPath('')->withQuery('')->withFragment('');
                $this->notifications->sendForgotPassword(
                    $user['email'],
                    $user['username'],
                    $password,
                    $siteUrl,
                );
            }
        }

        // Always redirect with a generic confirmation (no user enumeration)
        return $response->withHeader('Location', app_url('/forgot-password?sent=1'))->withStatus(302);
    }

    // ── POST /logout ──────────────────────────────────────────────

    public function logout(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $csrf = (string) ($body['csrf_token'] ?? '');

        if (!csrf_verify($csrf)) {
            return $response->withHeader('Location', app_url('/'))->withStatus(302);
        }

        if (!empty($_SESSION['user'])) {
            // Remove the stored session token from DB so it can't be reused
            $this->users->setSessionToken((int) $_SESSION['user']['id'], null);
        }

        // Destroy session
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly'],
            );
        }
        session_destroy();

        return $response->withHeader('Location', app_url('/login'))->withStatus(302);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function renderLogin(
        Response $response,
        string   $error,
        string   $next       = '',
        string   $identifier = '',
    ): Response {
        return $this->twig->render($response, 'auth/login.twig', [
            'error'                 => $error,
            'next'                  => $next,
            'forgotPasswordEnabled' => $this->forgotPasswordEnabled(),
            'magicLinkEnabled'      => $this->magicLinkEnabled(),
            // Restore password phase when the form is re-rendered after an error
            'loginPhase'            => $identifier !== '' ? 'password' : 'identifier',
            'loginIdentifier'       => $identifier,
        ]);
    }

    /**
     * Return true when the self-service password reset is available:
     * the feature must be enabled in settings AND SMTP must be verified working.
     */
    private function forgotPasswordEnabled(): bool
    {
        return $this->settings->getBool('allow_password_reset')
            && $this->notifications->smtpReady();
    }

    /**
     * Return true when magic-link sign-in is available:
     * the feature must be enabled in settings AND SMTP must be verified working.
     */
    private function magicLinkEnabled(): bool
    {
        return $this->settings->getBool('allow_magic_link')
            && $this->notifications->smtpReady();
    }

    /** Encode a JSON response. */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * Validate that a redirect target is a safe local path.
     * Rejects empty strings, external URLs, and protocol-relative URLs.
     */
    private function sanitizeNext(string $url): string
    {
        if ($url === '') {
            return '';
        }

        // Must start with a single slash (not //)
        if ($url[0] !== '/' || (strlen($url) > 1 && $url[1] === '/')) {
            return '';
        }

        // Allow only safe path characters
        if (!preg_match('/^\/[a-zA-Z0-9\/\-_.~%?=&]*$/', $url)) {
            return '';
        }

        return $url;
    }

    /**
     * Return the client's IP address.
     * Trusts X-Forwarded-For only when behind a known proxy; falls back to
     * REMOTE_ADDR for direct connections (typical on shared hosting).
     */
    private function clientIp(Request $request): string
    {
        // Use REMOTE_ADDR — the most reliable value on shared hosting.
        // X-Forwarded-For is not trusted here to avoid IP spoofing attacks
        // by clients crafting the header themselves.
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
