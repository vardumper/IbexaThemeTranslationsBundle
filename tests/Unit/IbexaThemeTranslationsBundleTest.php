<?php

declare(strict_types=1);

use vardumper\IbexaThemeTranslationsBundle\IbexaThemeTranslationsBundle;

uses(PHPUnit\Framework\TestCase::class);

it('getPath returns the bundle root directory', function () {
    $bundle = new IbexaThemeTranslationsBundle();

    expect($bundle->getPath())->toBe(dirname(__DIR__, 2));
});
