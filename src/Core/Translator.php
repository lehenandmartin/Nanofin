<?php

declare(strict_types=1);

namespace Nanofin\Core;

/**
 * Simple key-based translator.
 *
 * Translation files live in translations/{locale}.php and return an
 * associative array:  ['key' => 'translated string', ...]
 *
 * Keys support dot-notation: 'auth.login.title' maps to
 *   ['auth' => ['login' => ['title' => '...']]]
 *
 * Placeholders use :name syntax: 'Hello, :name!' with ['name' => 'Alice'].
 */
final class Translator
{
    /** @var array<string, array<string, mixed>> */
    private array $catalogue = [];

    private string $locale;
    private string $fallback;
    private string $translationsDir;

    public function __construct(
        string $locale          = 'en',
        string $fallback        = 'en',
        ?string $translationsDir = null,
    ) {
        $this->locale          = $locale;
        $this->fallback        = $fallback;
        $this->translationsDir = $translationsDir ?? (defined('TRANSLATIONS_DIR')
            ? TRANSLATIONS_DIR
            : dirname(__DIR__, 2) . '/translations');
    }

    // ── Public API ────────────────────────────────────────────────

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * Translate a key with optional parameters.
     *
     * @param array<string, string|int|float> $params
     */
    public function trans(string $key, array $params = [], ?string $locale = null): string
    {
        $locale ??= $this->locale;

        $value = $this->resolve($key, $locale)
            ?? $this->resolve($key, $this->fallback)
            ?? $key;  // fallback to the key itself

        if ($params !== []) {
            foreach ($params as $placeholder => $replacement) {
                $value = str_replace(':' . $placeholder, (string) $replacement, $value);
            }
        }

        return $value;
    }

    /** Return all available locales (files present in translations/). */
    public function availableLocales(): array
    {
        if (!is_dir($this->translationsDir)) {
            return [$this->fallback];
        }
        $locales = [];
        foreach (glob($this->translationsDir . '/*.php') ?: [] as $file) {
            $locales[] = basename($file, '.php');
        }
        sort($locales);
        return $locales ?: [$this->fallback];
    }

    // ── Internals ─────────────────────────────────────────────────

    private function resolve(string $key, string $locale): ?string
    {
        $this->load($locale);

        $catalogue = $this->catalogue[$locale] ?? [];

        // Try flat key first
        if (isset($catalogue[$key]) && is_string($catalogue[$key])) {
            return $catalogue[$key];
        }

        // Try dot-notation traversal
        $parts  = explode('.', $key);
        $cursor = $catalogue;
        foreach ($parts as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                return null;
            }
            $cursor = $cursor[$part];
        }

        return is_string($cursor) ? $cursor : null;
    }

    private function load(string $locale): void
    {
        if (isset($this->catalogue[$locale])) {
            return;
        }

        $file = $this->translationsDir . '/' . $locale . '.php';

        if (!file_exists($file)) {
            $this->catalogue[$locale] = [];
            return;
        }

        $data = require $file;
        $this->catalogue[$locale] = is_array($data) ? $data : [];
    }
}
