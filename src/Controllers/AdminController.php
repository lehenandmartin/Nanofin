<?php

declare(strict_types=1);

namespace Nanofin\Controllers;

use Nanofin\Core\DiscordService;
use Nanofin\Core\JellyfinService;
use Nanofin\Core\NotificationService;
use Nanofin\Core\Translator;
use Nanofin\Models\DownloadModel;
use Nanofin\Models\SessionModel;
use Nanofin\Models\SettingsModel;
use Nanofin\Models\UserModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class AdminController
{
    public function __construct(
        private readonly Twig                $twig,
        private readonly UserModel           $users,
        private readonly SettingsModel       $settings,
        private readonly DownloadModel       $downloads,
        private readonly JellyfinService     $jellyfin,
        private readonly Translator          $translator,
        private readonly NotificationService $notifications,
        private readonly DiscordService      $discord,
        private readonly SessionModel        $sessions,
    ) {}

    // ── Dashboard ─────────────────────────────────────────────────

    public function index(Request $request, Response $response): Response
    {
        // Quick Jellyfin connection check (non-fatal)
        $jellyfinOk = false;
        $jellyfinError = null;
        try {
            $this->jellyfin->testConnection();
            $jellyfinOk = true;
        } catch (\Throwable $e) {
            $jellyfinError = $e->getMessage();
        }

        return $this->twig->render($response, 'admin/index.twig', [
            'adminSection'    => '/admin',
            'userCount'       => $this->users->count(),
            'downloadCount'   => $this->downloads->count(),
            'recentDownloads' => $this->downloads->recent(8),
            'jellyfinOk'      => $jellyfinOk,
            'jellyfinError'   => $jellyfinError,
        ]);
    }

    // ── Settings ──────────────────────────────────────────────────

    public function settings(Request $request, Response $response): Response
    {
        // URL check: server reachable + is Jellyfin (no API key needed)
        $serverName = $this->jellyfin->pingServer(); // null on failure
        // API key check: full authenticated connection
        $apiKeyOk   = false;
        try {
            $this->jellyfin->testConnection();
            $apiKeyOk = true;
        } catch (\Throwable) {}

        // Group timezones by continent for the select
        $timezones = [];
        foreach (\DateTimeZone::listIdentifiers() as $tz) {
            $slash = strpos($tz, '/');
            $group = $slash !== false ? substr($tz, 0, $slash) : 'Other';
            $timezones[$group][] = $tz;
        }
        ksort($timezones);

        // SMTP status — read from the result persisted at last save time
        // (no live test here; the test runs in saveSettings())
        $smtpOk        = $this->settings->get('smtp_ok') === '1';
        $smtpLastError = $this->settings->get('smtp_last_error', '');
        $smtpHostError = null; // connectivity problem  → shown under Host / Port
        $smtpAuthError = null; // authentication failure → shown under User / Password

        if (!$smtpOk && $smtpLastError !== '') {
            // PHPMailer auth errors always contain the word "authenticat"
            if (str_contains(strtolower($smtpLastError), 'authenticat')) {
                $smtpAuthError = $smtpLastError;
            } else {
                $smtpHostError = $smtpLastError;
            }
        }

        $discordOk        = $this->settings->get('discord_webhook_ok') === '1';
        $discordLastError = $this->settings->get('discord_webhook_last_error', '');

        return $this->twig->render($response, 'admin/settings.twig', [
            'adminSection'     => '/admin/settings',
            's'                => $this->settings->all(),
            'locales'          => $this->translator->availableLocales(),
            'serverName'       => $serverName,
            'apiKeyOk'         => $apiKeyOk,
            'smtpOk'           => $smtpOk,
            'smtpHostError'    => $smtpHostError,
            'smtpAuthError'    => $smtpAuthError,
            'timezones'        => $timezones,
            'discordOk'        => $discordOk,
            'discordLastError' => $discordLastError,
        ]);
    }

    public function saveSettings(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        if (!csrf_verify((string) ($body['csrf_token'] ?? ''))) {
            return $response->withHeader('Location', app_url('/admin/settings'))->withStatus(302);
        }

        $allowed = [
            'jellyfin_url', 'jellyfin_api_key',
            'site_title', 'public_mode', 'allow_password_reset', 'allow_magic_link', 'grid_rows', 'poster_cache_days', 'timezone',
            'default_locale', 'default_sort', 'session_max_days',
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_password', 'smtp_from',
            'discord_webhook_url', 'discord_notify_downloads',
        ];

        $checkboxKeys = ['public_mode', 'allow_password_reset', 'allow_magic_link', 'discord_notify_downloads'];

        $data = [];
        foreach ($allowed as $key) {
            // Checkboxes send nothing when unchecked — treat as '0'
            if (in_array($key, $checkboxKeys, true)) {
                $data[$key] = isset($body[$key]) ? '1' : '0';
            } else {
                $data[$key] = trim((string) ($body[$key] ?? ''));
            }
        }

        // Validate sort value against known keys
        if (!in_array($data['default_sort'], ['title', 'year', 'added', 'rating'], true)) {
            $data['default_sort'] = 'added';
        }

        // session_max_days must be a non-negative integer
        $data['session_max_days'] = (string) max(0, (int) $data['session_max_days']);

        // Validate timezone — fall back to UTC if the submitted value is invalid
        if (!in_array($data['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            $data['timezone'] = 'UTC';
        }

        // __KEEP__ sentinel: masked fields not changed in the UI — preserve the stored value
        $prevUrl        = $this->settings->get('jellyfin_url');
        $prevKey        = $this->settings->get('jellyfin_api_key');
        $prevDiscordUrl = $this->settings->get('discord_webhook_url');

        if ($data['jellyfin_api_key'] === '__KEEP__') {
            $data['jellyfin_api_key'] = $prevKey;
        }
        if ($data['smtp_password'] === '__KEEP__') {
            $data['smtp_password'] = $this->settings->get('smtp_password');
        }
        if ($data['discord_webhook_url'] === '__KEEP__') {
            $data['discord_webhook_url'] = $prevDiscordUrl;
        }

        // If Jellyfin URL or API key changed, invalidate the cached user ID
        if ($data['jellyfin_url'] !== $prevUrl || $data['jellyfin_api_key'] !== $prevKey) {
            $this->jellyfin->invalidateUserIdCache();
        }

        $this->settings->setMany($data);

        // If Discord webhook URL changed, clear the previous test result and disable notifications
        if ($data['discord_webhook_url'] !== $prevDiscordUrl) {
            $this->settings->set('discord_webhook_ok', '0');
            $this->settings->set('discord_webhook_last_error', '');
            $this->settings->set('discord_notify_downloads', '0');
        }

        // ── SMTP connection test ──────────────────────────────────────
        // Runs once at save time with the freshly-saved config; the result
        // is persisted so the settings page never re-tests on every load.
        if ($data['smtp_host'] !== '') {
            // Use a temporary MailService built from the submitted data so we
            // test the new credentials even before the DI container is rebuilt.
            $tempMailer    = new \Nanofin\Core\MailService($data);
            $smtpResult    = $tempMailer->testSmtp();
            $smtpOk        = ($smtpResult === true);
            $smtpLastError = is_string($smtpResult) ? $smtpResult : '';
        } else {
            // Host cleared → mark as not verified
            $smtpOk        = false;
            $smtpLastError = '';
        }
        $this->settings->set('smtp_ok', $smtpOk ? '1' : '0');
        $this->settings->set('smtp_last_error', $smtpLastError);

        // Opportunistically clean up stale poster cache files using the
        // freshly saved cache duration.  Settings saves are infrequent so
        // the extra I/O is acceptable.
        try {
            $cacheDays = (int) ($data['poster_cache_days'] ?? 7);
            $this->jellyfin->cleanPosterCache($cacheDays);
        } catch (\Throwable) {
            // Non-fatal — cache cleanup failure must never block settings save
        }

        // Reload the locale for the current admin session
        if (isset($data['default_locale'])) {
            $_SESSION['locale'] = $data['default_locale'];
            $this->translator->setLocale($data['default_locale']);
        }

        flash('success', $this->translator->trans('admin.settings.saved'));

        return $response->withHeader('Location', app_url('/admin/settings'))->withStatus(302);
    }

    // ── Users ─────────────────────────────────────────────────────

    public function users(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'admin/users.twig', [
            'adminSection'   => '/admin/users',
            'userList'       => $this->users->all(),
            'smtpConfigured' => $this->notifications->smtpReady(),
        ]);
    }

    public function createUser(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        if (!csrf_verify((string) ($body['csrf_token'] ?? ''))) {
            return $response->withHeader('Location', app_url('/admin/users'))->withStatus(302);
        }

        $username      = trim((string) ($body['username']       ?? ''));
        $password      = trim((string) ($body['password']         ?? ''));
        $email         = strtolower(trim((string) ($body['email'] ?? '')));
        $role          = in_array($body['role'] ?? '', ['admin', 'user'], true)
                         ? $body['role'] : 'user';
        $contentAccess = in_array($body['content_access'] ?? '', ['movies', 'shows', 'both'], true)
                         ? $body['content_access'] : 'both';
        $ageLimitRaw   = trim((string) ($body['age_limit'] ?? ''));
        $ageLimit      = $ageLimitRaw !== '' ? (int) $ageLimitRaw : null;
        if ($ageLimit !== null && ($ageLimit < 0 || $ageLimit > 18)) {
            $ageLimit = null;
        }

        $errors = [];

        if ($username === '') {
            $errors[] = $this->translator->trans('setup.validation.username_required');
        } elseif (mb_strlen($username) > 50 || preg_match('/[\x00-\x1F\x7F]/', $username)) {
            $errors[] = $this->translator->trans('admin.users.username_invalid');
        } elseif ($this->users->findByUsername($username) !== null) {
            $errors[] = $username . ' already exists.';
        }

        if (mb_strlen($password) < 8) {
            $errors[] = $this->translator->trans('setup.validation.password_min');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = $this->translator->trans('setup.validation.email_invalid');
        } elseif ($email !== '' && $this->users->emailExists($email)) {
            $errors[] = $email . ' is already in use.';
        }

        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            return $response->withHeader('Location', app_url('/admin/users'))->withStatus(302);
        }

        $hash   = password_hash($password, PASSWORD_BCRYPT);
        $userId = $this->users->create($username, $hash, $role, $contentAccess, $email, $ageLimit);

        if (!empty($body['force_password_change'])) {
            $this->users->setForcePasswordChange($userId, true);
        }

        // Optional invitation email
        if (!empty($body['send_invitation']) && $email !== '' && $this->notifications->smtpConfigured()) {
            $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $siteUrl = $scheme . '://' . $host;
            $this->notifications->sendInvitation($email, $username, $password, $siteUrl);
        }

        flash('success', $this->translator->trans('admin.users.created'));

        return $response->withHeader('Location', app_url('/admin/users'))->withStatus(302);
    }

    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        $body   = (array) $request->getParsedBody();
        $userId = (int) ($args['id'] ?? 0);
        $meId   = (int) ($_SESSION['user']['id'] ?? 0);

        if (!csrf_verify((string) ($body['csrf_token'] ?? '')) || $userId === $meId) {
            flash('error', $userId === $meId ? 'You cannot delete your own account.' : 'Invalid request.');
            return $response->withHeader('Location', app_url('/admin/users'))->withStatus(302);
        }

        $target = $this->users->findById($userId);
        if ($target !== null && $target['role'] === 'admin' && $this->users->countAdmins() <= 1) {
            flash('error', $this->translator->trans('admin.users.delete_last_admin'));
            return $response->withHeader('Location', app_url('/admin/users'))->withStatus(302);
        }

        $this->users->delete($userId);
        flash('success', $this->translator->trans('admin.users.deleted'));

        return $response->withHeader('Location', app_url('/admin/users'))->withStatus(302);
    }

    /**
     * POST /admin/users/{id}
     * Updates username, email, role and/or content_access — JSON response.
     */
    public function updateUser(Request $request, Response $response, array $args): Response
    {
        $userId = (int) ($args['id'] ?? 0);
        $meId   = (int) ($_SESSION['user']['id'] ?? 0);

        $body = (array) $request->getParsedBody();
        if (!csrf_verify((string) ($body['csrf_token'] ?? ''))) {
            return $this->jsonResponse($response, ['error' => 'Invalid CSRF token'], 403);
        }

        $target = $this->users->findById($userId);
        if ($target === null) {
            return $this->jsonResponse($response, ['error' => 'User not found'], 404);
        }

        // ── Validate username ─────────────────────────────────────
        $username = trim((string) ($body['username'] ?? ''));
        if ($username === '') {
            return $this->jsonResponse($response, [
                'error' => $this->translator->trans('setup.validation.username_required'),
            ], 422);
        }
        if (mb_strlen($username) > 50 || preg_match('/[\x00-\x1F\x7F]/', $username)) {
            return $this->jsonResponse($response, [
                'error' => $this->translator->trans('admin.users.username_invalid'),
            ], 422);
        }
        if ($username !== $target['username']) {
            $existing = $this->users->findByUsername($username);
            if ($existing !== null && (int) $existing['id'] !== $userId) {
                return $this->jsonResponse($response, [
                    'error' => $this->translator->trans('admin.users.username_taken'),
                ], 422);
            }
        }

        // ── Validate email ────────────────────────────────────────
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonResponse($response, [
                'error' => $this->translator->trans('setup.validation.email_invalid'),
            ], 422);
        }
        if ($email !== '' && $this->users->emailExists($email, $userId)) {
            return $this->jsonResponse($response, [
                'error' => $this->translator->trans('profile.email.already_in_use'),
            ], 422);
        }

        // ── Validate role & access ────────────────────────────────
        $role = isset($body['role']) && in_array($body['role'], ['admin', 'user'], true)
            ? $body['role'] : $target['role'];

        $contentAccess = isset($body['content_access'])
            && in_array($body['content_access'], ['movies', 'shows', 'both'], true)
            ? $body['content_access'] : $target['content_access'];

        $ageLimitRaw = trim((string) ($body['age_limit'] ?? ''));
        $newAgeLimit = $ageLimitRaw !== '' ? (int) $ageLimitRaw : null;
        if ($newAgeLimit !== null && ($newAgeLimit < 0 || $newAgeLimit > 18)) {
            $newAgeLimit = null;
        }

        // Prevent self-demotion
        if ($userId === $meId && $role !== $target['role']) {
            return $this->jsonResponse($response, [
                'error' => $this->translator->trans('admin.users.reset_password.self_error'),
            ], 400);
        }

        // ── Persist ───────────────────────────────────────────────
        if ($username !== $target['username']) {
            $this->users->updateUsername($userId, $username);
        }

        if ($email !== ($target['email'] ?? '')) {
            $this->users->updateEmail($userId, $email);
        }

        $securityChanged = false;
        if ($role !== $target['role']) {
            $this->users->updateRole($userId, $role);
            $securityChanged = true;
        }
        if ($contentAccess !== $target['content_access']) {
            $this->users->updateContentAccess($userId, $contentAccess);
            $securityChanged = true;
        }

        $currentAgeLimit = $target['age_limit'] !== null ? (int) $target['age_limit'] : null;
        if ($newAgeLimit !== $currentAgeLimit) {
            $this->users->updateAgeLimit($userId, $newAgeLimit);
            $securityChanged = true;
        }

        // Invalidate all sessions on security-sensitive changes (not for own account)
        if ($securityChanged && $userId !== $meId) {
            $this->sessions->deleteByUser($userId);
        }

        return $this->jsonResponse($response, ['success' => true, 'username' => $username]);
    }

    // ── Logs ──────────────────────────────────────────────────────

    public function logs(Request $request, Response $response): Response
    {
        $params     = $request->getQueryParams();
        $filterUser = isset($params['user']) ? (int) $params['user'] : null;
        $page       = max(1, (int) ($params['page'] ?? 1));
        $limit      = 50;
        $offset     = ($page - 1) * $limit;

        $total = $this->downloads->count($filterUser);
        $pages = (int) ceil($total / $limit) ?: 1;
        $logs  = $this->downloads->all($filterUser, $limit, $offset);

        return $this->twig->render($response, 'admin/logs.twig', [
            'adminSection' => '/admin/logs',
            'logs'         => $logs,
            'total'        => $total,
            'page'         => $page,
            'pages'        => $pages,
            'filterUser'   => $filterUser,
            'allUsers'     => $this->users->all(),
        ]);
    }

    public function clearLogs(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        if (!csrf_verify((string) ($body['csrf_token'] ?? ''))) {
            return $response->withHeader('Location', app_url('/admin/logs'))->withStatus(302);
        }

        $this->downloads->clearAll();
        flash('success', $this->translator->trans('admin.logs.cleared'));

        return $response->withHeader('Location', app_url('/admin/logs'))->withStatus(302);
    }

    // ── Email test ────────────────────────────────────────────────

    public function testEmail(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        if (!csrf_verify((string) ($body['csrf_token'] ?? ''))) {
            return $response->withHeader('Location', app_url('/admin/settings'))->withStatus(302);
        }

        $to = trim((string) ($body['test_email_to'] ?? ''));

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', $this->translator->trans('setup.validation.email_invalid'));
            return $response->withHeader('Location', app_url('/admin/settings'))->withStatus(302);
        }

        try {
            $this->notifications->sendTest($to);
            flash('success', "Test email sent to {$to}.");
        } catch (\Throwable $e) {
            flash('error', 'Mail error: ' . $e->getMessage());
        }

        return $response->withHeader('Location', app_url('/admin/settings'))->withStatus(302);
    }

    // ── Discord webhook test ──────────────────────────────────────

    public function testDiscord(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        if (!csrf_verify((string) ($body['csrf_token'] ?? ''))) {
            return $response->withHeader('Location', app_url('/admin/settings'))->withStatus(302);
        }

        try {
            $this->discord->sendTest();
            $this->settings->set('discord_webhook_ok', '1');
            $this->settings->set('discord_webhook_last_error', '');
            flash('success', $this->translator->trans('admin.settings.discord_test_sent'));
        } catch (\Throwable $e) {
            $this->settings->set('discord_webhook_ok', '0');
            $this->settings->set('discord_webhook_last_error', $e->getMessage());
            flash('error', $e->getMessage());
        }

        return $response->withHeader('Location', app_url('/admin/settings'))->withStatus(302);
    }

    // ── Password reset ────────────────────────────────────────────

    /**
     * POST /admin/users/{id}/password
     * Generates a new password, saves it, and returns JSON.
     * The admin sees the password in the modal before deciding to email it.
     */
    public function resetPassword(Request $request, Response $response, array $args): Response
    {
        $userId = (int) ($args['id'] ?? 0);
        $meId   = (int) ($_SESSION['user']['id'] ?? 0);

        $body = (array) $request->getParsedBody();
        if (!csrf_verify((string) ($body['csrf_token'] ?? ''))) {
            return $this->jsonResponse($response, ['error' => 'Invalid CSRF token'], 403);
        }

        if ($userId === $meId) {
            return $this->jsonResponse($response, [
                'error' => $this->translator->trans('admin.users.reset_password.self_error'),
            ], 400);
        }

        $target = $this->users->findById($userId);
        if ($target === null) {
            return $this->jsonResponse($response, ['error' => 'User not found'], 404);
        }

        $newPassword = trim((string) ($body['password'] ?? ''));
        if (mb_strlen($newPassword) < 8) {
            return $this->jsonResponse($response,
                ['error' => $this->translator->trans('setup.validation.password_min')], 400);
        }

        $this->users->updatePassword($userId, password_hash($newPassword, PASSWORD_BCRYPT));
        $this->sessions->deleteByUser($userId);
        $this->users->setForcePasswordChange($userId, ($body['force_password_change'] ?? '0') === '1');

        $smtpReady = $this->notifications->smtpReady();
        $hasEmail  = ($target['email'] ?? '') !== '' && $smtpReady;

        return $this->jsonResponse($response, [
            'success'   => true,
            'username'  => $target['username'],
            'password'  => $newPassword,
            'hasEmail'  => $hasEmail,
            'smtpReady' => $smtpReady,
        ]);
    }

    /**
     * POST /admin/users/{id}/password/email
     * Sends the (already-reset) password to the user's email address.
     * The cleartext password is passed back by the modal.
     */
    public function sendPasswordEmail(Request $request, Response $response, array $args): Response
    {
        $userId = (int) ($args['id'] ?? 0);

        $body = (array) $request->getParsedBody();
        if (!csrf_verify((string) ($body['csrf_token'] ?? ''))) {
            return $this->jsonResponse($response, ['error' => 'Invalid CSRF token'], 403);
        }

        $password = (string) ($body['password'] ?? '');
        if ($password === '') {
            return $this->jsonResponse($response, ['error' => 'No password provided'], 400);
        }

        $target = $this->users->findById($userId);
        if ($target === null) {
            return $this->jsonResponse($response, ['error' => 'User not found'], 404);
        }

        $userEmail = $target['email'] ?? '';
        if ($userEmail === '') {
            return $this->jsonResponse($response, ['error' => 'No email address for this user'], 400);
        }

        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $siteUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        try {
            $this->notifications->sendPasswordReset($userEmail, $target['username'], $password, $siteUrl);
        } catch (\Throwable $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }

        return $this->jsonResponse($response, ['success' => true]);
    }

    // ── Sessions ──────────────────────────────────────────────────

    public function revokeSessions(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        if (!csrf_verify((string) ($body['csrf_token'] ?? ''))) {
            return $response->withHeader('Location', app_url('/admin'))->withStatus(302);
        }

        $meId = (int) ($_SESSION['user']['id'] ?? 0);
        $this->sessions->deleteAllExceptUser($meId);

        flash('success', $this->translator->trans('admin.sessions.revoked'));

        return $response->withHeader('Location', app_url('/admin/users'))->withStatus(302);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
