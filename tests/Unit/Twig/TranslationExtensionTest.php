<?php

declare(strict_types=1);

use fork\IbexaThemeTranslationsBundle\Twig\TranslationExtension;
use Twig\TwigFilter;

uses(PHPUnit\Framework\TestCase::class);

it('registers exactly one filter named l10n', function () {
    $extension = new TranslationExtension();
    $filters = $extension->getFilters();

    expect($filters)->toHaveCount(1);
    expect($filters[0])->toBeInstanceOf(TwigFilter::class);
    expect($filters[0]->getName())->toBe('l10n');
});
