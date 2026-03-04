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
            new TwigFilter('l10n', [TranslationRuntime::class, 'l10n'], [
                'is_safe' => ['html'],
            ]),
        ];
    }
}
