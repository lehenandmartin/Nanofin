<?php

declare(strict_types=1);

namespace Nanofin\Controllers;

use Nanofin\Core\Translator;
use Nanofin\Models\SessionModel;
use Nanofin\Models\SettingsModel;
use Nanofin\Models\UserModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class SetupController
{
    public function __construct(
        private readonly Twig          $twig,
        private readonly UserModel     $users,
        private readonly SettingsModel $settings,
        private readonly Translator    $translator,
        private readonly SessionModel  $sessions,
    ) {}

    // ── GET /setup ────────────────────────────────────────────────

    public function index(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'auth/setup.twig', [
            'requirements' => $this->checkRequirements(),
        ]);
    }

    // ── POST /setup ───────────────────────────────────────────────

    public function store(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        // ── CSRF ──────────────────────────────────────────────────
        if (!csrf_verify((string) ($body['csrf_token'] ?? ''))) {
            return $this->renderWithErrors($response, $body, [
                'general' => $this->translator->trans('auth.login.invalid'),
            ]);
        }

        $errors = $this->validate($body);
        if ($errors !== []) {
            return $this->renderWithErrors($response, $body, $errors);
        }

        // ── Persist ───────────────────────────────────────────────
        $username     = trim((string) $body['username']);
        $password     = (string) $body['password'];
        $email        = strtolower(trim((string) $body['email']));
        $jellyfinUrl  = rtrim(trim((string) $body['jellyfin_url']), '/');
        $jellyfinKey  = trim((string) $body['jellyfin_api_key']);
        $siteTitle    = trim((string) ($body['site_title'] ?? 'Nanofin'));

        // Create admin user
        $hash   = password_hash($password, PASSWORD_BCRYPT);
        $userId = $this->users->create($username, $hash, 'admin', 'both', $email);

        // Persist settings
        $this->settings->setMany([
            'site_title'       => $siteTitle ?: 'Nanofin',
            'jellyfin_url'     => $jellyfinUrl,
            'jellyfin_api_key' => $jellyfinKey,
        ]);

        // ── Auto-login the new admin ──────────────────────────────
        $sessionToken = bin2hex(random_bytes(16));
        $this->sessions->create(
            $userId,
            $sessionToken,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        );

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'             => $userId,
            'username'       => $username,
            'role'           => 'admin',
            'content_access' => 'both',
            'session_token'  => $sessionToken,
        ];
        $_SESSION['locale'] = 'en';

        $this->users->updateLastActivity($userId);

        flash('success', $this->translator->trans('setup.success'));

        return $response->withHeader('Location', app_url('/admin'))->withStatus(302);
    }

    // ── Validation ────────────────────────────────────────────────

    /** @return array<string, string> field → error message */
    private function validate(array $body): array
    {
        $errors   = [];
        $t        = $this->translator;

        $username    = trim((string) ($body['username']        ?? ''));
        $password    = (string) ($body['password']             ?? '');
        $email       = trim((string) ($body['email']           ?? ''));
        $jellyfinUrl = trim((string) ($body['jellyfin_url']    ?? ''));
        $jellyfinKey = trim((string) ($body['jellyfin_api_key'] ?? ''));

        if ($username === '') {
            $errors['username'] = $t->trans('setup.validation.username_required');
        } elseif ($this->users->findByUsername($username) !== null) {
            // Shouldn't happen (setup only runs once) but defensive
            $errors['username'] = $t->trans('setup.validation.username_required');
        }

        if ($password === '') {
            $errors['password'] = $t->trans('setup.validation.password_required');
        } elseif (mb_strlen($password) < 8) {
            $errors['password'] = $t->trans('setup.validation.password_min');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = $t->trans('setup.validation.email_invalid');
        }

        if ($jellyfinUrl === '') {
            $errors['jellyfin_url'] = $t->trans('setup.validation.jellyfin_url_required');
        } elseif (!filter_var($jellyfinUrl, FILTER_VALIDATE_URL)) {
            $errors['jellyfin_url'] = $t->trans('setup.validation.jellyfin_url_required');
        }

        if ($jellyfinKey === '') {
            $errors['jellyfin_api_key'] = $t->trans('setup.validation.api_key_required');
        }

        return $errors;
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function renderWithErrors(Response $response, array $old, array $errors): Response
    {
        return $this->twig->render($response, 'auth/setup.twig', [
            'errors'       => $errors,
            'old'          => $old,
            'requirements' => $this->checkRequirements(),
        ]);
    }

    /**
     * Check server requirements and return a list of checks.
     *
     * @return array{items: list<array{key: string, ok: bool, value: string}>, all_ok: bool}
     */
    private function checkRequirements(): array
    {
        $items = [
            [
                'key'   => 'php',
                'ok'    => version_compare(PHP_VERSION, '8.2.0', '>='),
                'value' => PHP_VERSION,
            ],
            [
                'key'   => 'pdo_sqlite',
                'ok'    => extension_loaded('pdo_sqlite'),
                'value' => '',
            ],
            [
                'key'   => 'mbstring',
                'ok'    => extension_loaded('mbstring'),
                'value' => '',
            ],
            [
                'key'   => 'openssl',
                'ok'    => extension_loaded('openssl'),
                'value' => '',
            ],
            [
                'key'   => 'data_dir',
                'ok'    => is_writable(DATA_DIR),
                'value' => '',
            ],
            [
                'key'   => 'cache_dir',
                'ok'    => is_writable(CACHE_DIR),
                'value' => '',
            ],
            [
                'key'   => 'posters_dir',
                'ok'    => is_writable(POSTER_CACHE_DIR),
                'value' => '',
            ],
        ];

        $allOk = array_reduce($items, static fn(bool $carry, array $item): bool => $carry && $item['ok'], true);

        return ['items' => $items, 'all_ok' => $allOk];
    }
}
