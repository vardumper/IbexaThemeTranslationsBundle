<?php

declare(strict_types=1);

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
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

/** PSR-6 pool mock whose getItem() returns the given item. */
function makePool(CacheItemInterface $item): CacheItemPoolInterface
{
    return (new class('test') extends PHPUnit\Framework\TestCase {
        public function pool(CacheItemInterface $item): CacheItemPoolInterface
        {
            $pool = $this->createMock(CacheItemPoolInterface::class);
            $pool->method('getItem')->willReturn($item);

            return $pool;
        }
    })->pool($item);
}

function makeHitItem(array $map): CacheItemInterface
{
    return (new class('test') extends PHPUnit\Framework\TestCase {
        public function item(array $map): CacheItemInterface
        {
            $item = $this->createMock(CacheItemInterface::class);
            $item->method('isHit')->willReturn(true);
            $item->method('get')->willReturn($map);

            return $item;
        }
    })->item($map);
}

function makeMissItem(): CacheItemInterface
{
    return (new class('test') extends PHPUnit\Framework\TestCase {
        public function item(): CacheItemInterface
        {
            $item = $this->createMock(CacheItemInterface::class);
            $item->method('isHit')->willReturn(false);

            return $item;
        }
    })->item();
}

it('returns the value from the static cache (tier 1) without touching Redis or DB', function () {
    $dir = sys_get_temp_dir() . '/svc_trans_test_' . uniqid('', true);
    $static = new StaticArrayTranslationCache($dir);
    $static->warmLanguage('eng-GB', ['hello' => 'Hello']);

    $pool = makePool(makeMissItem());
    $redis = new RedisTranslationCache($pool, 'prefix');

    $repo = $this->createMock(TranslationRepository::class);
    $repo->expects($this->never())->method('translateOrNull');

    $warmer = new TranslationCacheWarmer($repo, []);
    $service = new TranslationService($static, $redis, $repo, $warmer);

    expect($service->translate('hello', 'eng-GB'))->toBe('Hello');
});

it('falls through to Redis (tier 2) when the static cache misses', function () {
    $dir = sys_get_temp_dir() . '/svc_trans_test_' . uniqid('', true);
    $static = new StaticArrayTranslationCache($dir); // empty → always misses

    $pool = makePool(makeHitItem(['hello' => 'Hello']));
    $redis = new RedisTranslationCache($pool, 'prefix');

    $repo = $this->createMock(TranslationRepository::class);
    $repo->expects($this->never())->method('translateOrNull');

    $warmer = new TranslationCacheWarmer($repo, []);
    $service = new TranslationService($static, $redis, $repo, $warmer);

    expect($service->translate('hello', 'eng-GB'))->toBe('Hello');
});

it('falls through to the database (tier 3) and warms all tiers when both caches miss', function () {
    $dir = sys_get_temp_dir() . '/svc_trans_test_' . uniqid('', true);
    $static = new StaticArrayTranslationCache($dir);

    // Build a RedisTranslationCache that always simulates a miss
    $pool = makePool(makeMissItem());
    $redis = new RedisTranslationCache($pool, 'prefix');

    $repo = $this->createMock(TranslationRepository::class);
    $repo->expects($this->once())->method('translateOrNull')
        ->with('hello', 'eng-GB')->willReturn('Hello');
    $repo->method('findAllByLanguageCodeAsKeyValueMap')
        ->willReturn(['hello' => 'Hello']);

    $warmer = new TranslationCacheWarmer($repo, [$static]);
    $service = new TranslationService($static, $redis, $repo, $warmer);

    expect($service->translate('hello', 'eng-GB'))->toBe('Hello');
    expect($static->get('eng-GB', 'hello'))->toBe('Hello');
});

it('returns the key itself and does not re-warm when the DB has no row for the key', function () {
    $dir = sys_get_temp_dir() . '/svc_trans_test_' . uniqid('', true);
    $static = new StaticArrayTranslationCache($dir);

    $pool = makePool(makeMissItem());
    $redis = new RedisTranslationCache($pool, 'prefix');

    $repo = $this->createMock(TranslationRepository::class);
    // Exactly one DB lookup for both service calls — the second is served from the in-request memo.
    $repo->expects($this->once())->method('translateOrNull')
        ->with('missing.key', 'eng-GB')->willReturn(null);
    $repo->expects($this->never())->method('findAllByLanguageCodeAsKeyValueMap');

    $warmer = new TranslationCacheWarmer($repo, [$static]);
    $service = new TranslationService($static, $redis, $repo, $warmer);

    expect($service->translate('missing.key', 'eng-GB'))->toBe('missing.key')
        ->and($service->translate('missing.key', 'eng-GB'))->toBe('missing.key');
});

it('skips the Redis tier entirely when no RedisTranslationCache is configured', function () {
    $dir = sys_get_temp_dir() . '/svc_trans_test_' . uniqid('', true);
    $static = new StaticArrayTranslationCache($dir);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('translateOrNull')->willReturn(null);

    $warmer = new TranslationCacheWarmer($repo, []);
    $service = new TranslationService($static, null, $repo, $warmer);

    expect($service->translate('hello', 'eng-GB'))->toBe('hello');
});
