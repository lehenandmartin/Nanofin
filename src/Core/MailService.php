<?php

declare(strict_types=1);

namespace Nanofin\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;
use RuntimeException;

/**
 * Thin wrapper around PHPMailer.
 * All SMTP configuration comes from the database settings table.
 */
final class MailService
{
    /** @param array<string, string> $config */
    public function __construct(private readonly array $config) {}

    /**
     * Send an email.
     *
     * @param string|array<int, string> $to  One address or [address, name]
     * @throws RuntimeException on failure
     */
    public function send(
        string|array $to,
        string       $subject,
        string       $htmlBody,
        string       $plainBody = '',
    ): void {
        if (empty($this->config['smtp_host'])) {
            throw new RuntimeException('SMTP is not configured.');
        }

        $mailer = $this->createMailer();

        // Recipient
        if (is_array($to)) {
            $mailer->addAddress($to[0], $to[1] ?? '');
        } else {
            $mailer->addAddress($to);
        }

        $mailer->Subject  = $subject;
        $mailer->Body     = $htmlBody;
        $mailer->AltBody  = $plainBody ?: strip_tags($htmlBody);

        try {
            $mailer->send();
        } catch (MailerException $e) {
            throw new RuntimeException('Mail send failed: ' . $mailer->ErrorInfo, 0, $e);
        }
    }

    // ── Connection test ───────────────────────────────────────────

    /**
     * Open and immediately close an SMTP connection to verify credentials.
     * Uses a short timeout so the admin settings page stays responsive.
     *
     * Returns:
     *   null   — SMTP host is not configured (nothing to test)
     *   true   — connection succeeded
     *   string — connection failed; the value is a human-readable error message
     */
    public function testSmtp(): true|string|null
    {
        if (empty($this->config['smtp_host'])) {
            return null;
        }

        $mailer          = $this->createMailer();
        $mailer->Timeout = 5; // seconds — short timeout for a status check

        try {
            $connected = $mailer->smtpConnect();
            if ($connected) {
                $mailer->smtpClose();
                return true;
            }
            return $mailer->ErrorInfo ?: 'Connection failed.';
        } catch (\Throwable $e) {
            return $e->getMessage() ?: 'Unknown error.';
        }
    }

    // ── Factory ───────────────────────────────────────────────────

    private function createMailer(): PHPMailer
    {
        $mailer = new PHPMailer(exceptions: true);

        $mailer->isSMTP();
        $mailer->CharSet  = PHPMailer::CHARSET_UTF8;
        $mailer->isHTML(true);

        $mailer->Host       = $this->config['smtp_host']     ?? '';
        $mailer->Port       = (int) ($this->config['smtp_port']     ?? 587);
        $mailer->Username   = $this->config['smtp_user']     ?? '';
        $mailer->Password   = $this->config['smtp_password'] ?? '';
        $mailer->SMTPAuth   = ($mailer->Username !== '');
        $mailer->SMTPSecure = $mailer->Port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;

        $fromAddress = $this->config['smtp_from'] ?? ($mailer->Username ?: 'noreply@example.com');
        $mailer->setFrom($fromAddress, $this->config['site_title'] ?? 'Nanofin');

        return $mailer;
    }
}
