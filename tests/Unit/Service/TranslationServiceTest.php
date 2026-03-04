<?php

declare(strict_types=1);

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use vardumper\IbexaThemeTranslationsBundle\Cache\RedisTranslationCache;
use vardumper\IbexaThemeTranslationsBundle\Cache\StaticArrayTranslationCache;
use vardumper\IbexaThemeTranslationsBundle\Cache\TranslationCacheWarmer;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationRepository;
use vardumper\IbexaThemeTranslationsBundle\Service\TranslationService;

uses(PHPUnit\Framework\TestCase::class);

afterEach(function () {
    foreach (glob(sys_get_temp_dir() . '/svc_trans_test_*') ?: [] as $dir) {
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
});

it('returns the value from the static cache (tier 1) without touching Redis or DB', function () {
    $dir = sys_get_temp_dir() . '/svc_trans_test_' . uniqid('', true);
    $static = new StaticArrayTranslationCache($dir);
    $static->warmLanguage('eng-GB', ['hello' => 'Hello']);

    $pool = $this->createMock(CacheInterface::class);
    $pool->expects($this->never())->method('get');
    $redis = new RedisTranslationCache($pool, 'prefix');

    $repo = $this->createMock(TranslationRepository::class);
    $repo->expects($this->never())->method('translate');

    $warmer = new TranslationCacheWarmer($repo, []);
    $service = new TranslationService($static, $redis, $repo, $warmer);

    expect($service->translate('hello', 'eng-GB'))->toBe('Hello');
});

it('falls through to Redis (tier 2) when the static cache misses', function () {
    $dir = sys_get_temp_dir() . '/svc_trans_test_' . uniqid('', true);
    $static = new StaticArrayTranslationCache($dir); // empty → always misses

    $pool = $this->createMock(CacheInterface::class);
    $pool->method('get')->willReturn('Hello');
    $redis = new RedisTranslationCache($pool, 'prefix');

    $repo = $this->createMock(TranslationRepository::class);
    $repo->expects($this->never())->method('translate');

    $warmer = new TranslationCacheWarmer($repo, []);
    $service = new TranslationService($static, $redis, $repo, $warmer);

    expect($service->translate('hello', 'eng-GB'))->toBe('Hello');
});

it('falls through to the database (tier 3) and warms all tiers when both caches miss', function () {
    $dir = sys_get_temp_dir() . '/svc_trans_test_' . uniqid('', true);
    $static = new StaticArrayTranslationCache($dir);

    // Build a RedisTranslationCache that always simulates a miss
    $item = $this->createMock(ItemInterface::class);
    $missPool = $this->createMock(CacheInterface::class);
    $missPool->method('get')->willReturnCallback(
        static function (string $key, callable $cb) use ($item) {
            return $cb($item);
        }
    );
    $redis = new RedisTranslationCache($missPool, 'prefix');

    $repo = $this->createMock(TranslationRepository::class);
    $repo->expects($this->once())->method('translate')
        ->with('hello', 'eng-GB')->willReturn('Hello');
    $repo->method('findAllByLanguageCodeAsKeyValueMap')
        ->willReturn(['hello' => 'Hello']);

    $warmer = new TranslationCacheWarmer($repo, [$static]);
    $service = new TranslationService($static, $redis, $repo, $warmer);

    expect($service->translate('hello', 'eng-GB'))->toBe('Hello');
    expect($static->get('eng-GB', 'hello'))->toBe('Hello');
});
