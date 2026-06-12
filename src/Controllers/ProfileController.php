<?php

declare(strict_types=1);

namespace Nanofin\Controllers;

use Nanofin\Core\Translator;
use Nanofin\Models\SessionModel;
use Nanofin\Models\UserModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class ProfileController
{
    public function __construct(
        private readonly Twig         $twig,
        private readonly UserModel    $users,
        private readonly Translator   $translator,
        private readonly SessionModel $sessions,
    ) {}

    // ── GET /account ──────────────────────────────────────────────

    public function index(Request $request, Response $response): Response
    {
        $user = $this->requireUser();
        if ($user === null) {
            return $response->withHeader('Location', app_url('/login?next=/account'))->withStatus(302);
        }

        $dbUser = $this->users->findById((int) $user['id']);

        return $this->twig->render($response, 'profile/index.twig', [
            'email' => $dbUser['email'] ?? '',
        ]);
    }

    // ── POST /account/password ────────────────────────────────────

    public function changePassword(Request $request, Response $response): Response
    {
        $user = $this->requireUser();
        if ($user === null) {
            return $response->withHeader('Location', app_url('/login'))->withStatus(302);
        }

        $body    = (array) $request->getParsedBody();
        $current = (string) ($body['current_password'] ?? '');
        $new     = (string) ($body['new_password']     ?? '');
        $confirm = (string) ($body['confirm_password'] ?? '');

        if (!csrf_verify((string) ($body['csrf_token'] ?? ''))) {
            return $response->withHeader('Location', app_url('/account'))->withStatus(302);
        }

        // Verify current password
        $dbUser = $this->users->findById((int) $user['id']);
        if ($dbUser === null || !password_verify($current, $dbUser['password'])) {
            flash('error', $this->translator->trans('profile.password.wrong_current'));
            return $response->withHeader('Location', app_url('/account'))->withStatus(302);
        }

        if (mb_strlen($new) < 8) {
            flash('error', $this->translator->trans('profile.password.too_short'));
            return $response->withHeader('Location', app_url('/account'))->withStatus(302);
        }

        if ($new !== $confirm) {
            flash('error', $this->translator->trans('profile.password.mismatch'));
            return $response->withHeader('Location', app_url('/account'))->withStatus(302);
        }

        $this->users->updatePassword((int) $user['id'], password_hash($new, PASSWORD_BCRYPT));

        flash('success', $this->translator->trans('profile.password.success'));
        return $response->withHeader('Location', app_url('/account'))->withStatus(302);
    }

    // ── POST /account/email ───────────────────────────────────────

    public function changeEmail(Request $request, Response $response): Response
    {
        $user = $this->requireUser();
        if ($user === null) {
            return $response->withHeader('Location', app_url('/login'))->withStatus(302);
        }

        $body  = (array) $request->getParsedBody();
        $email = strtolower(trim((string) ($body['email'] ?? '')));

        if (!csrf_verify((string) ($body['csrf_token'] ?? ''))) {
            return $response->withHeader('Location', app_url('/account'))->withStatus(302);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', $this->translator->trans('setup.validation.email_invalid'));
            return $response->withHeader('Location', app_url('/account'))->withStatus(302);
        }

        $userId = (int) $user['id'];

        if ($this->users->emailExists($email, $userId)) {
            flash('error', $this->translator->trans('profile.email.already_in_use'));
            return $response->withHeader('Location', app_url('/account'))->withStatus(302);
        }

        $this->users->updateEmail($userId, $email);
        flash('success', $this->translator->trans('profile.email.updated'));

        return $response->withHeader('Location', app_url('/account'))->withStatus(302);
    }

    // ── GET /account/sessions ─────────────────────────────────────

    public function getSessions(Request $request, Response $response): Response
    {
        $user = $this->requireUser();
        if ($user === null) {
            return $this->jsonResponse($response, ['error' => 'Unauthorized'], 401);
        }

        $userId       = (int) $user['id'];
        $currentToken = (string) ($user['session_token'] ?? '');
        $rows         = $this->sessions->getByUser($userId);

        $sessions = array_map(fn($row) => [
            'id'            => (int) $row['id'],
            'label'         => SessionModel::parseUserAgent((string) $row['user_agent']),
            'ip'            => (string) $row['ip'],
            'current'       => $row['token'] === $currentToken,
            'last_activity' => (string) $row['last_activity'],
            'created_at'    => (string) $row['created_at'],
        ], $rows);

        return $this->jsonResponse($response, ['sessions' => $sessions]);
    }

    // ── POST /account/sessions/{id}/revoke ────────────────────────

    public function revokeSession(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireUser();
        if ($user === null) {
            return $this->jsonResponse($response, ['error' => 'Unauthorized'], 401);
        }

        $body = (array) $request->getParsedBody();
        if (!csrf_verify((string) ($body['csrf_token'] ?? ''))) {
            return $this->jsonResponse($response, ['error' => 'Invalid CSRF token'], 403);
        }

        $sessionId = (int) ($args['id'] ?? 0);
        $userId    = (int) $user['id'];

        // Verify the session belongs to this user before deleting
        if ($this->sessions->findById($sessionId, $userId) === null) {
            return $this->jsonResponse($response, ['error' => 'Session not found'], 404);
        }

        $this->sessions->deleteById($sessionId);

        return $this->jsonResponse($response, ['ok' => true]);
    }

    // ── POST /account/sessions/revoke-others ──────────────────────

    public function revokeOtherSessions(Request $request, Response $response): Response
    {
        $user = $this->requireUser();
        if ($user === null) {
            return $this->jsonResponse($response, ['error' => 'Unauthorized'], 401);
        }

        $body = (array) $request->getParsedBody();
        if (!csrf_verify((string) ($body['csrf_token'] ?? ''))) {
            return $this->jsonResponse($response, ['error' => 'Invalid CSRF token'], 403);
        }

        $userId       = (int) $user['id'];
        $currentToken = (string) ($user['session_token'] ?? '');

        $currentSession = $this->sessions->findByToken($currentToken);
        if ($currentSession !== null) {
            $this->sessions->deleteAllExceptSession($userId, (int) $currentSession['id']);
        }

        return $this->jsonResponse($response, ['ok' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * Return the session user array, or null if not logged in.
     *
     * @return array{id:int,username:string,role:string,content_access:string}|null
     */
    private function requireUser(): ?array
    {
        $user = $_SESSION['user'] ?? null;
        return is_array($user) ? $user : null;
    }
}
