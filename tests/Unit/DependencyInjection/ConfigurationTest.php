<?php

declare(strict_types=1);

use Symfony\Component\Config\Definition\Processor;
use vardumper\IbexaThemeTranslationsBundle\DependencyInjection\Configuration;

uses(PHPUnit\Framework\TestCase::class);

it('returns defaults when no config is provided', function () {
    $processor = new Processor();
    $config = $processor->processConfiguration(new Configuration(), []);

    expect($config['cache_dir'])->toBe('%kernel.cache_dir%/theme_translations');
    expect($config['default_language'])->toBe('eng-GB');
    expect($config['redis']['enabled'])->toBeTrue();
    expect($config['redis']['prefix'])->toBe('theme_trans');
});

it('accepts custom cache_dir and default_language', function () {
    $processor = new Processor();
    $config = $processor->processConfiguration(new Configuration(), [
        [
            'cache_dir' => '/custom/cache',
            'default_language' => 'deu-DE',
        ],
    ]);

    expect($config['cache_dir'])->toBe('/custom/cache');
    expect($config['default_language'])->toBe('deu-DE');
});

it('accepts redis configuration overrides', function () {
    $processor = new Processor();
    $config = $processor->processConfiguration(new Configuration(), [
        [
            'redis' => [
                'enabled' => false,
                'prefix' => 'my_prefix',
            ],
        ],
    ]);

    expect($config['redis']['enabled'])->toBeFalse();
    expect($config['redis']['prefix'])->toBe('my_prefix');
});

it('merges multiple config arrays with last-wins semantics', function () {
    $processor = new Processor();
    $config = $processor->processConfiguration(new Configuration(), [
        ['default_language' => 'eng-GB'],
        ['default_language' => 'fre-FR'],
    ]);

    expect($config['default_language'])->toBe('fre-FR');
});
