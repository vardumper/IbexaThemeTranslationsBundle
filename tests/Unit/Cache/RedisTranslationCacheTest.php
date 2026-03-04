<?php

declare(strict_types=1);

use fork\IbexaThemeTranslationsBundle\Cache\RedisTranslationCache;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

uses(PHPUnit\Framework\TestCase::class);

it('returns null on a cache miss (sentinel is returned)', function () {
    $item = $this->createMock(ItemInterface::class);
    $cache = $this->createMock(CacheInterface::class);
    $cache->method('get')
        ->willReturnCallback(function (string $key, callable $callback) use ($item) {
            return $callback($item);
        });
    $redis = new RedisTranslationCache($cache, 'prefix');

    expect($redis->get('eng-GB', 'hello'))->toBeNull();
});

it('returns the value when the cache holds a real string', function () {
    $cache = $this->createMock(CacheInterface::class);
    $cache->method('get')->willReturn('Hello');

    $redis = new RedisTranslationCache($cache, 'prefix');

    expect($redis->get('eng-GB', 'hello'))->toBe('Hello');
});

it('warms a language by writing each key to the cache', function () {
    $item = $this->createMock(ItemInterface::class);
    $item->expects($this->exactly(2))->method('tag');
    $item->expects($this->exactly(2))->method('expiresAfter')->with(86400);

    $cache = $this->createMock(CacheInterface::class);
    $cache->expects($this->exactly(2))->method('delete');
    $cache->expects($this->exactly(2))
        ->method('get')
        ->willReturnCallback(function (string $key, callable $callback) use ($item) {
            return $callback($item);
        });

    $redis = new RedisTranslationCache($cache, 'pfx');
    $redis->warmLanguage('eng-GB', ['hello' => 'Hello', 'bye' => 'Bye']);
});

it('invalidates a language via tag invalidation when the pool supports it', function () {
    $cache = $this->createMock(TagAwareCacheInterface::class);
    $cache->expects($this->once())
        ->method('invalidateTags')
        ->with(['theme_trans_lang_eng-GB']);

    $redis = new RedisTranslationCache($cache, 'prefix');
    $redis->invalidateLanguage('eng-GB');
});

it('skips tag invalidation when the pool does not support it', function () {
    $cache = $this->createMock(CacheInterface::class);
    // Should not throw; no invalidateTags available
    $redis = new RedisTranslationCache($cache, 'prefix');
    $redis->invalidateLanguage('eng-GB');

    expect(true)->toBeTrue();
});

it('clears the cache pool on invalidateAll when clear() exists', function () {
    // Create an anonymous class that implements both CacheInterface and has clear()
    $cache = new class() implements CacheInterface {
        public bool $cleared = false;

        public function get(string $key, callable $callback, float $beta = null, array &$metadata = null): mixed
        {
            return null;
        }

        public function delete(string $key): bool
        {
            return true;
        }

        public function clear(): bool
        {
            $this->cleared = true;
            return true;
        }
    };

    $redis = new RedisTranslationCache($cache, 'prefix');
    $redis->invalidateAll();

    expect($cache->cleared)->toBeTrue();
});
