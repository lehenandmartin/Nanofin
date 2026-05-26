<?php

declare(strict_types=1);

namespace Nanofin\Controllers;

use Nanofin\Core\Translator;
use Nanofin\Models\UserModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class ProfileController
{
    public function __construct(
        private readonly Twig       $twig,
        private readonly UserModel  $users,
        private readonly Translator $translator,
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

    // ── Helpers ───────────────────────────────────────────────────

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
