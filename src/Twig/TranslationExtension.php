<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class TranslationExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // Deliberately NOT marked is_safe: translation values are user-entered DB content,
            // so Twig must escape them to prevent stored XSS. If rich-text translations are ever
            // needed, add a separate explicit filter for that use case instead.
            new TwigFilter('l10n', [TranslationRuntime::class, 'l10n']),
        ];
    }
}
