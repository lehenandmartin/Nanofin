<?php

declare(strict_types=1);

namespace Nanofin\Core;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * Twig extension that exposes the Translator as a global `trans()` function.
 *
 * Usage in templates:
 *   {{ trans('auth.login.title') }}
 *   {{ trans('auth.welcome', {name: user.username}) }}
 */
final class TranslatorExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly Translator $translator) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('trans', $this->trans(...)),
        ];
    }

    public function getGlobals(): array
    {
        return [
            'locale'           => $this->translator->getLocale(),
            'availableLocales' => $this->translator->availableLocales(),
        ];
    }

    /**
     * @param array<string, string|int|float> $params
     */
    public function trans(string $key, array $params = []): string
    {
        return $this->translator->trans($key, $params);
    }
}
