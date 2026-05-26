<?php

declare(strict_types=1);

namespace Nanofin\Core;

use Nanofin\Models\SettingsModel;
use Slim\Views\Twig;

/**
 * NotificationService — composes and sends transactional emails.
 *
 * All sends are non-blocking (wrapped in try/catch) so a broken SMTP
 * configuration never breaks the main user flow.
 *
 * The service first checks that SMTP is configured (smtp_host non-empty)
 * before attempting any send.
 */
final class NotificationService
{
    public function __construct(
        private readonly MailService   $mailer,
        private readonly SettingsModel $settings,
        private readonly Twig          $twig,
        private readonly Translator    $translator,
    ) {}

    // ── Public API ────────────────────────────────────────────────

    /**
     * Send a welcome / invitation email to a newly-created user.
     *
     * @param string $to        The new user's email address
     * @param string $username  Their username
     * @param string $password  Cleartext password (sent once at creation time)
     * @param string $siteUrl   The public URL of the Nanofin instance
     */
    public function sendInvitation(
        string $to,
        string $username,
        string $password,
        string $siteUrl,
    ): void {
        if (!$this->smtpReady()) {
            return;
        }

        $siteName = $this->settings->get('site_title', 'Nanofin');
        $loginUrl = rtrim($siteUrl, '/') . '/login';
        $locale   = $this->appLocale();
        $t        = fn(string $k, array $p = []) => $this->translator->trans($k, $p, $locale);

        try {
            $html = $this->render('emails/invitation.twig', [
                'siteName' => $siteName,
                'username' => $username,
                'password' => $password,
                'loginUrl' => $loginUrl,
            ], $locale);

            $plain = $t('email.greeting', ['username' => $username]) . "\n\n"
                   . $t('email.invitation.intro', ['site' => $siteName]) . "\n\n"
                   . $t('email.label_url')      . ': ' . $loginUrl . "\n"
                   . $t('email.label_username') . ': ' . $username . "\n"
                   . $t('email.label_password') . ': ' . $password . "\n\n"
                   . $t('email.invitation.change_password');

            $this->mailer->send(
                to:        $to,
                subject:   $t('email.invitation.subject', ['site' => $siteName]),
                htmlBody:  $html,
                plainBody: $plain,
            );
        } catch (\Throwable) {
            // Non-fatal — user was already created successfully
        }
    }

    /**
     * Notify a user that their password has been reset by an admin.
     *
     * @param string $to        The user's primary email address
     * @param string $username  Their username
     * @param string $password  The new cleartext password
     * @param string $siteUrl   The public URL of the Nanofin instance
     */
    public function sendPasswordReset(
        string $to,
        string $username,
        string $password,
        string $siteUrl,
    ): void {
        if (!$this->smtpReady()) {
            return;
        }

        $siteName = $this->settings->get('site_title', 'Nanofin');
        $loginUrl = rtrim($siteUrl, '/') . '/login';
        $locale   = $this->appLocale();
        $t        = fn(string $k, array $p = []) => $this->translator->trans($k, $p, $locale);

        try {
            $html = $this->render('emails/password_reset.twig', [
                'siteName'    => $siteName,
                'username'    => $username,
                'password'    => $password,
                'loginUrl'    => $loginUrl,
                'selfService' => false,
            ], $locale);

            $plain = $t('email.greeting', ['username' => $username]) . "\n\n"
                   . $t('email.password_reset.intro_admin', ['site' => $siteName]) . "\n\n"
                   . $t('email.label_url')      . ': ' . $loginUrl . "\n"
                   . $t('email.label_username') . ': ' . $username . "\n"
                   . $t('email.label_password') . ': ' . $password . "\n\n"
                   . $t('email.password_reset.change_password');

            $this->mailer->send(
                to:        $to,
                subject:   $t('email.password_reset.subject', ['site' => $siteName]),
                htmlBody:  $html,
                plainBody: $plain,
            );
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    /**
     * Send a magic-link sign-in email.
     *
     * @param string $to       The user's email address
     * @param string $username Their username (for personalisation)
     * @param string $magicUrl Full URL including the raw token
     */
    public function sendMagicLink(
        string $to,
        string $username,
        string $magicUrl,
    ): void {
        if (!$this->smtpReady()) {
            return;
        }

        $siteName = $this->settings->get('site_title', 'Nanofin');
        $locale   = $this->appLocale();
        $t        = fn(string $k, array $p = []) => $this->translator->trans($k, $p, $locale);

        try {
            $html = $this->render('emails/magic_link.twig', [
                'siteName' => $siteName,
                'username' => $username,
                'magicUrl' => $magicUrl,
            ], $locale);

            $plain = $t('email.greeting', ['username' => $username]) . "\n\n"
                   . $t('email.magic_link.intro', ['site' => $siteName]) . "\n"
                   . $magicUrl . "\n\n"
                   . $t('email.magic_link.expires') . "\n"
                   . $t('email.magic_link.ignore');

            $this->mailer->send(
                to:        $to,
                subject:   $t('email.magic_link.subject', ['site' => $siteName]),
                htmlBody:  $html,
                plainBody: $plain,
            );
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    /**
     * Notify a user that they requested a password reset themselves.
     *
     * @param string $to        The user's email address
     * @param string $username  Their username
     * @param string $password  The new cleartext temporary password
     * @param string $siteUrl   The public URL of the Nanofin instance
     */
    public function sendForgotPassword(
        string $to,
        string $username,
        string $password,
        string $siteUrl,
    ): void {
        if (!$this->smtpReady()) {
            return;
        }

        $siteName = $this->settings->get('site_title', 'Nanofin');
        $loginUrl = rtrim($siteUrl, '/') . '/login';
        $locale   = $this->appLocale();
        $t        = fn(string $k, array $p = []) => $this->translator->trans($k, $p, $locale);

        try {
            $html = $this->render('emails/password_reset.twig', [
                'siteName'    => $siteName,
                'username'    => $username,
                'password'    => $password,
                'loginUrl'    => $loginUrl,
                'selfService' => true,
            ], $locale);

            $plain = $t('email.greeting', ['username' => $username]) . "\n\n"
                   . $t('email.password_reset.intro_self', ['site' => $siteName]) . "\n\n"
                   . $t('email.label_url')      . ': ' . $loginUrl . "\n"
                   . $t('email.label_username') . ': ' . $username . "\n"
                   . $t('email.label_password') . ': ' . $password . "\n\n"
                   . $t('email.password_reset.change_password');

            $this->mailer->send(
                to:        $to,
                subject:   $t('email.password_reset.subject_self', ['site' => $siteName]),
                htmlBody:  $html,
                plainBody: $plain,
            );
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    /**
     * Send a test email to verify SMTP is working.
     *
     * @throws \RuntimeException if SMTP is not configured or the send fails
     */
    public function sendTest(string $to): void
    {
        if (!$this->smtpConfigured()) {
            throw new \RuntimeException('SMTP is not configured. Please fill in the SMTP settings first.');
        }

        $siteName = $this->settings->get('site_title', 'Nanofin');
        // Test email uses the current (admin) locale, not the app default
        $locale   = $this->translator->getLocale();
        $t        = fn(string $k, array $p = []) => $this->translator->trans($k, $p, $locale);

        $html = $this->render('emails/test.twig', [
            'siteName' => $siteName,
        ], $locale);

        // sendTest propagates exceptions (unlike other methods)
        $this->mailer->send(
            to:        $to,
            subject:   $t('email.test.subject', ['site' => $siteName]),
            htmlBody:  $html,
            plainBody: $t('email.test.intro', ['site' => $siteName]) . ' '
                     . $t('email.test.success'),
        );
    }

    // ── Helpers ───────────────────────────────────────────────────

    /**
     * Return true if SMTP host is configured (host non-empty).
     * Does NOT verify that the connection works — use smtpReady() for that.
     */
    public function smtpConfigured(): bool
    {
        return $this->settings->get('smtp_host') !== '';
    }

    /**
     * Return true if SMTP is configured AND was verified to be working
     * on the last settings save (smtp_ok = '1').
     * Use this to gate all email-dependent features.
     */
    public function smtpReady(): bool
    {
        return $this->settings->get('smtp_host') !== ''
            && $this->settings->get('smtp_ok') === '1';
    }

    /**
     * Test the SMTP connection live.
     *
     * Returns:
     *   null   — SMTP not configured (host is empty)
     *   true   — configured + connected successfully
     *   string — configured + connection failed; the value is the error message
     *
     * @internal Used by AdminController::saveSettings() to run the test at
     *           save time. Not called on every page load.
     */
    public function testSmtp(): true|string|null
    {
        return $this->mailer->testSmtp();
    }

    /**
     * Render an email template in the given locale.
     * Temporarily switches the Translator locale so trans() calls inside the
     * template return the right language, then restores the original locale.
     * Also injects `locale` as an explicit variable so base.twig can set
     * the correct <html lang=""> attribute.
     */
    private function render(string $template, array $vars, string $locale): string
    {
        $previous = $this->translator->getLocale();
        $this->translator->setLocale($locale);
        try {
            return $this->twig->getEnvironment()->render(
                $template,
                ['locale' => $locale] + $vars,
            );
        } finally {
            $this->translator->setLocale($previous);
        }
    }

    /**
     * Return the application's default locale (used for all user-facing emails).
     */
    private function appLocale(): string
    {
        return $this->settings->get('default_locale', 'en');
    }
}
