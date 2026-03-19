<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use vardumper\IbexaThemeTranslationsBundle\DependencyInjection\IbexaThemeTranslationsExtension;

uses(PHPUnit\Framework\TestCase::class);

function makeContainer(): ContainerBuilder
{
    $container = new ContainerBuilder();
    // Provide the kernel.cache_dir parameter that Configuration references
    $container->setParameter('kernel.cache_dir', '/tmp/cache');

    return $container;
}

it('sets the cache_dir container parameter', function () {
    $container = makeContainer();
    (new IbexaThemeTranslationsExtension())->load([], $container);

    expect($container->getParameter('ibexa_theme_translations.cache_dir'))
        ->toBe('%kernel.cache_dir%/theme_translations');
});

it('sets the default_language container parameter', function () {
    $container = makeContainer();
    (new IbexaThemeTranslationsExtension())->load([], $container);

    expect($container->getParameter('ibexa_theme_translations.default_language'))
        ->toBe('eng-GB');
});

it('sets redis.enabled container parameter to true by default', function () {
    $container = makeContainer();
    (new IbexaThemeTranslationsExtension())->load([], $container);

    expect($container->getParameter('ibexa_theme_translations.redis.enabled'))
        ->toBeTrue();
});

it('sets redis.prefix container parameter', function () {
    $container = makeContainer();
    (new IbexaThemeTranslationsExtension())->load([], $container);

    expect($container->getParameter('ibexa_theme_translations.redis.prefix'))
        ->toBe('theme_trans');
});

it('respects config overrides passed to load()', function () {
    $container = makeContainer();
    (new IbexaThemeTranslationsExtension())->load(
        [['default_language' => 'deu-DE', 'redis' => ['enabled' => false, 'prefix' => 'custom']]],
        $container
    );

    expect($container->getParameter('ibexa_theme_translations.default_language'))->toBe('deu-DE');
    expect($container->getParameter('ibexa_theme_translations.redis.enabled'))->toBeFalse();
    expect($container->getParameter('ibexa_theme_translations.redis.prefix'))->toBe('custom');
});
