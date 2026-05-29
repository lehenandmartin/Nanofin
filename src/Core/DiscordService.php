<?php

declare(strict_types=1);

namespace Nanofin\Core;

use RuntimeException;

final class DiscordService
{
    /**
     * @param array<string, string> $config
     */
    public function __construct(
        private readonly array      $config,
        private readonly Translator $translator,
    ) {}

    public function isEnabled(): bool
    {
        return ($this->config['discord_webhook_url'] ?? '') !== ''
            && ($this->config['discord_notify_downloads'] ?? '0') === '1';
    }

    public function notifyDownload(string $itemTitle, string $itemType, ?string $username): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $locale    = $this->config['default_locale'] ?? 'en';
        $who       = $username ?? $this->translator->trans('discord.anonymous', [], $locale);
        $typeLabel = $this->translator->trans('discord.type_' . $itemType, [], $locale);
        $message   = $this->translator->trans('discord.download_message', [
            'user'  => $who,
            'title' => $itemTitle,
            'type'  => $typeLabel,
        ], $locale);

        try {
            $this->post([
                'embeds' => [[
                    'description' => $message,
                    'color'       => 5793266,
                    'timestamp'   => gmdate('Y-m-d\TH:i:s\Z'),
                ]],
            ]);
        } catch (\Throwable) {
            // Non-blocking — never interrupt the download
        }
    }

    public function sendTest(): void
    {
        $url = $this->config['discord_webhook_url'] ?? '';
        if ($url === '') {
            throw new RuntimeException('No Discord webhook URL configured.');
        }

        $siteName = $this->config['site_title'] ?? 'Nanofin';

        $this->post([
            'embeds' => [[
                'description' => "**{$siteName}** webhook test — connection successful.",
                'color'       => 5793266,
                'timestamp'   => gmdate('Y-m-d\TH:i:s\Z'),
            ]],
        ]);
    }

    private function buildBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = rtrim($_ENV['APP_BASE_PATH'] ?? '', '/');
        return $scheme . '://' . $host . $base;
    }

    /** @param array<mixed> $payload */
    private function post(array $payload): void
    {
        $url = $this->config['discord_webhook_url'] ?? '';

        $payload['username']   = $this->config['site_title'] ?? 'Nanofin';
        $payload['avatar_url'] = $this->buildBaseUrl() . '/apple-touch-icon.png';

        $body = (string) json_encode($payload);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);

        curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            throw new RuntimeException('Discord request failed: ' . $curlError);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("Discord webhook returned HTTP {$httpCode}.");
        }
    }
}
