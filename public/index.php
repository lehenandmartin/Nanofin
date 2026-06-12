<?php

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/settings.php';

use DI\ContainerBuilder;
use Nanofin\Controllers\AdminController;
use Nanofin\Controllers\AuthController;
use Nanofin\Controllers\DownloadController;
use Nanofin\Controllers\LibraryController;
use Nanofin\Controllers\ProfileController;
use Nanofin\Controllers\SetupController;
use Nanofin\Core\Database;
use Nanofin\Core\DiscordService;
use Nanofin\Core\JellyfinService;
use Nanofin\Core\MailService;
use Nanofin\Core\NotificationService;
use Nanofin\Core\Translator;
use Nanofin\Core\TranslatorExtension;
use Nanofin\Middleware\AdminMiddleware;
use Nanofin\Middleware\AuthMiddleware;
use Nanofin\Middleware\HttpErrorHandler;
use Nanofin\Middleware\SetupMiddleware;
use Nanofin\Models\AuthTokenModel;
use Nanofin\Models\DownloadModel;
use Nanofin\Models\LoginAttemptModel;
use Nanofin\Models\SessionModel;
use Nanofin\Models\SettingsModel;
use Nanofin\Models\UserModel;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

// ── Helpers (global functions used in controllers) ────────────────

function session_start_safe(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function csrf_token(): string
{
    session_start_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(string $token): bool
{
    session_start_safe();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate an internal URL that respects the APP_BASE_PATH subfolder setting.
 * Use this in PHP code (controllers, redirects).
 * In Twig templates use the url() function instead.
 */
function app_url(string $path): string
{
    static $base = null;
    if ($base === null) {
        $base = rtrim($_ENV['APP_BASE_PATH'] ?? '', '/');
    }
    return $base . '/' . ltrim($path, '/');
}

// Start session early so Twig globals have access to it
session_start_safe();
// Generate CSRF token once per session
csrf_token();

// Apply timezone from DB settings as early as possible so all date()
// and DateTime calls (including download logs) use the correct offset.
(function (): void {
    try {
        $tz = (new \Nanofin\Models\SettingsModel())->get('timezone', 'UTC');
        if ($tz !== '' && in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            date_default_timezone_set($tz);
        }
    } catch (\Throwable) {
        // DB not yet initialised (first run wizard) — use server default
    }
})();

// ── Auto-migrations ───────────────────────────────────────────────
// Runs any pending .sql migrations on every boot.
// Already-applied migrations are skipped, so this is a no-op in production.
// Errors are silently logged — a broken migration is surfaced at the DB layer.
(function (): void {
    try {
        \Nanofin\Core\MigrationRunner::runPending();
    } catch (\Throwable $e) {
        error_log('[Nanofin] Migration error: ' . $e->getMessage());
    }
})();

// ── Probabilistic expired-session cleanup (~1 % of requests) ─────────
(function (): void {
    if (mt_rand(1, 100) !== 1) {
        return;
    }
    try {
        $maxDays = (int) (new \Nanofin\Models\SettingsModel())->get('session_max_days', '30');
        if ($maxDays > 0) {
            (new \Nanofin\Models\SessionModel())->deleteExpired($maxDays);
        }
    } catch (\Throwable $e) {
        error_log('[Nanofin] Session cleanup error: ' . $e->getMessage());
    }
})();

// ── Probabilistic poster cache cleanup (~1 % of requests) ─────────
// Deletes cached poster files older than poster_cache_days (default 30).
// Runs in the background so it never delays the response.
(function (): void {
    if (mt_rand(1, 100) !== 1) {
        return;
    }
    try {
        $days = (int) (new \Nanofin\Models\SettingsModel())->get('poster_cache_days', '30');
        if ($days <= 0) {
            return;
        }
        $cutoff = time() - ($days * 86400);
        foreach (glob(POSTER_CACHE_DIR . '/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    } catch (\Throwable $e) {
        error_log('[Nanofin] Poster cache cleanup error: ' . $e->getMessage());
    }
})();

// ── DI Container ─────────────────────────────────────────────────
$builder = new ContainerBuilder();
$builder->addDefinitions([

    // PSR-7 response factory — required by middlewares
    ResponseFactoryInterface::class => fn() => new ResponseFactory(),

    // PDO
    PDO::class => fn() => Database::getInstance(),

    // Models
    UserModel::class         => fn() => new UserModel(),
    SettingsModel::class     => fn() => new SettingsModel(),
    DownloadModel::class     => fn() => new DownloadModel(),
    LoginAttemptModel::class => fn() => new LoginAttemptModel(),
    AuthTokenModel::class    => fn() => new AuthTokenModel(),
    SessionModel::class      => fn() => new SessionModel(),

    // Translator — locale from session, fallback to DB default_locale
    Translator::class => static function (SettingsModel $settings): Translator {
        $locale = $_SESSION['locale'] ?? $settings->get('default_locale', 'en');
        return new Translator($locale);
    },

    // JellyfinService — built from DB settings
    JellyfinService::class => static function (SettingsModel $settings): JellyfinService {
        return new JellyfinService(
            $settings->get('jellyfin_url'),
            $settings->get('jellyfin_api_key'),
        );
    },

    // MailService
    MailService::class => static function (SettingsModel $settings): MailService {
        return new MailService($settings->all());
    },

    // DiscordService
    DiscordService::class => static function (SettingsModel $settings, Translator $translator): DiscordService {
        return new DiscordService($settings->all(), $translator);
    },

    // NotificationService
    NotificationService::class => static function (
        MailService   $mailer,
        SettingsModel $settings,
        Twig          $twig,
        Translator    $translator,
    ): NotificationService {
        return new NotificationService($mailer, $settings, $twig, $translator);
    },

    // Twig
    Twig::class => static function (Translator $translator, SettingsModel $settings): Twig {
        $twig = Twig::create(TEMPLATES_DIR, [
            'cache'       => false,   // enable in prod: CACHE_DIR . '/twig'
            'auto_reload' => true,
        ]);

        $env = $twig->getEnvironment();
        $env->addExtension(new TranslatorExtension($translator));

        // Base path for subfolder installs (APP_BASE_PATH env var)
        $basePath = rtrim($_ENV['APP_BASE_PATH'] ?? '', '/');
        $env->addGlobal('base_path', $basePath);

        // url('/path') prepends the base path — use for all internal links in templates
        $env->addFunction(new \Twig\TwigFunction(
            'url',
            static function (string $path) use ($basePath): string {
                return $basePath . '/' . ltrim($path, '/');
            }
        ));

        // Globals available in every template
        $env->addGlobal('settings',        $settings->all());
        $env->addGlobal('csrf_token',      $_SESSION['csrf_token'] ?? '');
        $env->addGlobal('auth_user',       $_SESSION['user']       ?? null);
        $env->addGlobal('flash_messages',  $_SESSION['flash']      ?? []);
        $env->addGlobal('app_version',     APP_VERSION);
        $env->addGlobal('app_github_url',  APP_GITHUB_URL);

        // Consume flash messages after injecting them
        unset($_SESSION['flash']);

        return $twig;
    },

    // Middlewares
    SetupMiddleware::class => static function (
        ResponseFactoryInterface $factory,
        UserModel $users,
    ): SetupMiddleware {
        return new SetupMiddleware($factory, $users);
    },

    AuthMiddleware::class => static function (
        ResponseFactoryInterface $factory,
        SettingsModel $settings,
        UserModel $users,
        SessionModel $sessions,
    ): AuthMiddleware {
        return new AuthMiddleware($factory, $settings, $users, $sessions);
    },

    AdminMiddleware::class => static function (
        ResponseFactoryInterface $factory,
        UserModel $users,
    ): AdminMiddleware {
        return new AdminMiddleware($factory, $users);
    },
]);

$container = $builder->build();
AppFactory::setContainer($container);
$app = AppFactory::create();

// Subfolder install support — strip APP_BASE_PATH prefix before routing
$appBasePath = rtrim($_ENV['APP_BASE_PATH'] ?? '', '/');
if ($appBasePath !== '') {
    $app->setBasePath($appBasePath);
}

// ── Middleware stack ──────────────────────────────────────────────
// Order matters: outermost = last added.

$app->addRoutingMiddleware();
$app->add(TwigMiddleware::createFromContainer($app, Twig::class));

// SetupMiddleware wraps everything (first-run redirect)
$app->add(SetupMiddleware::class);

// Error middleware — catches all exceptions, renders Twig error pages
$errorMiddleware = $app->addErrorMiddleware(
    displayErrorDetails: (bool) ($_ENV['APP_DEBUG'] ?? false),
    logErrors:           true,
    logErrorDetails:     true,
);
$errorMiddleware->setDefaultErrorHandler(
    new HttpErrorHandler(
        $app->getCallableResolver(),
        $app->getResponseFactory(),
        $container,
    )
);

// ── Flash helper ──────────────────────────────────────────────────
function flash(string $type, string $message): void
{
    session_start_safe();
    $_SESSION['flash'][] = ['type' => $type, 'text' => $message];
}

// ── Routes ────────────────────────────────────────────────────────

// ── Setup wizard (no auth — guarded by SetupMiddleware) ──────────
$app->get('/setup',  [SetupController::class, 'index']);
$app->post('/setup', [SetupController::class, 'store']);

// ── Auth ──────────────────────────────────────────────────────────
$app->get('/login',            [AuthController::class, 'showLogin']);
$app->post('/login',           [AuthController::class, 'login']);
$app->post('/login/identify',  [AuthController::class, 'identify']);
$app->post('/logout',          [AuthController::class, 'logout']);
$app->get('/forgot-password',  [AuthController::class, 'showForgotPassword']);
$app->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$app->get('/auth/magic/{token}', [AuthController::class, 'magicLogin']);

// ── First-login password change — protected by AuthMiddleware ────────
$app->group('', function ($group) {
    $group->get('/first-login',  [AuthController::class, 'showFirstLogin']);
    $group->post('/first-login', [AuthController::class, 'firstLogin']);
})->add(AuthMiddleware::class);

// ── Library — protected by AuthMiddleware ─────────────────────────
$app->group('', function ($group) {
    $group->get('/',            [LibraryController::class, 'index']);
    $group->get('/movies/{id}', [LibraryController::class, 'showMovie']);
    $group->get('/shows/{id}',  [LibraryController::class, 'showShow']);
})->add(AuthMiddleware::class);

// ── Account — requires login even in public mode ──────────────────
$app->group('/account', function ($group) {
    $group->get('',                       [ProfileController::class, 'index']);
    $group->post('/password',             [ProfileController::class, 'changePassword']);
    $group->post('/email',                [ProfileController::class, 'changeEmail']);
    // Sessions — specific routes must be declared before parametric ones
    $group->get('/sessions',              [ProfileController::class, 'getSessions']);
    $group->post('/sessions/revoke-others', [ProfileController::class, 'revokeOtherSessions']);
    $group->post('/sessions/{id}/revoke', [ProfileController::class, 'revokeSession']);
})->add(AuthMiddleware::class);

// ── Downloads — protected by AuthMiddleware ───────────────────────
$app->group('', function ($group) {
    $group->get('/download/{id}', [DownloadController::class, 'download']);
    $group->get('/poster/{id}',   [DownloadController::class, 'poster']);
})->add(AuthMiddleware::class);

// ── Admin — protected by AdminMiddleware ──────────────────────────
$app->group('/admin', function ($group) {
    $group->get('',                       [AdminController::class, 'index']);
    $group->get('/settings',              [AdminController::class, 'settings']);
    $group->post('/settings',             [AdminController::class, 'saveSettings']);
    $group->post('/settings/test-email',    [AdminController::class, 'testEmail']);
    $group->post('/settings/test-discord', [AdminController::class, 'testDiscord']);
    $group->get('/users',                 [AdminController::class, 'users']);
    $group->post('/users',                [AdminController::class, 'createUser']);
    $group->post('/users/{id}',           [AdminController::class, 'updateUser']);
    $group->post('/users/{id}/delete',    [AdminController::class, 'deleteUser']);
    $group->post('/users/{id}/password',       [AdminController::class, 'resetPassword']);
    $group->post('/users/{id}/password/email', [AdminController::class, 'sendPasswordEmail']);
    $group->get('/logs',                  [AdminController::class, 'logs']);
    $group->post('/logs/clear',           [AdminController::class, 'clearLogs']);
    $group->post('/sessions/revoke',      [AdminController::class, 'revokeSessions']);
})->add(AdminMiddleware::class);

// ── Run ───────────────────────────────────────────────────────────
$app->run();
