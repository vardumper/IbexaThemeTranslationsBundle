<?php

declare(strict_types=1);

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use vardumper\IbexaThemeTranslationsBundle\Cache\RedisTranslationCache;

uses(PHPUnit\Framework\TestCase::class);

/** PSR-6 item mock with the given hit state and value. */
function makePsrItem(bool $hit, mixed $value = null): CacheItemInterface
{
    return (new class('test') extends PHPUnit\Framework\TestCase {
        public function item(bool $hit, mixed $value): CacheItemInterface
        {
            $mock = $this->createMock(CacheItemInterface::class);
            $mock->method('isHit')->willReturn($hit);
            $mock->method('get')->willReturn($value);

            return $mock;
        }
    })->item($hit, $value);
}

/**
 * Fake pool implementing both PSR-6 and Symfony's tag-aware contract (a real Redis
 * adapter does too — the two interfaces are unrelated in the type hierarchy).
 */
class TagAwarePoolFake implements CacheItemPoolInterface, TagAwareCacheInterface
{
    /** @var array<string, CacheItemInterface> */
    public array $items = [];

    /** @var list<array<int, string>> */
    public array $invalidatedTags = [];

    /** @var list<string> */
    public array $deletedKeys = [];

    public bool $cleared = false;

    public function getItem(string $key): CacheItemInterface
    {
        return $this->items[$key] ?? makePsrItem(false);
    }

    public function getItems(array $keys = []): iterable
    {
        return [];
    }

    public function hasItem(string $key): bool
    {
        return isset($this->items[$key]);
    }

    public function clear(): bool
    {
        $this->cleared = true;

        return true;
    }

    public function deleteItem(string $key): bool
    {
        $this->deletedKeys[] = $key;

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->deleteItem($key);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return true;
    }

    public function commit(): bool
    {
        return true;
    }

    public function get(string $key, callable $callback, ?float $beta = null, array &$metadata = null): mixed
    {
        $item = $this->getItem($key);

        return $item->isHit() ? $item->get() : $callback($item, static fn () => true);
    }

    public function delete(string $key): bool
    {
        return $this->deleteItem($key);
    }

    public function invalidateTags(array $tags): bool
    {
        $this->invalidatedTags[] = array_values($tags);

        return true;
    }
}

it('returns null on a cache miss', function () {
    $pool = $this->createMock(CacheItemPoolInterface::class);
    $pool->method('getItem')->willReturn(makePsrItem(false));

    $redis = new RedisTranslationCache($pool, 'prefix');

    expect($redis->get('eng-GB', 'hello'))->toBeNull();
});

it('returns the value when the language map holds the key', function () {
    $pool = $this->createMock(CacheItemPoolInterface::class);
    $pool->method('getItem')->willReturn(makePsrItem(true, ['hello' => 'Hello']));

    $redis = new RedisTranslationCache($pool, 'prefix');

    expect($redis->get('eng-GB', 'hello'))->toBe('Hello');
});

it('returns null when the key is absent from a hit language map', function () {
    $pool = $this->createMock(CacheItemPoolInterface::class);
    $pool->method('getItem')->willReturn(makePsrItem(true, ['other' => 'Other']));

    $redis = new RedisTranslationCache($pool, 'prefix');

    expect($redis->get('eng-GB', 'hello'))->toBeNull();
});

it('treats a corrupt (non-array) entry as a miss', function () {
    $pool = $this->createMock(CacheItemPoolInterface::class);
    $pool->method('getItem')->willReturn(makePsrItem(true, 'legacy-string-value'));

    $redis = new RedisTranslationCache($pool, 'prefix');

    expect($redis->get('eng-GB', 'hello'))->toBeNull();
});

it('warms a language with one tagged item plus the languages manifest on first warm', function () {
    // Symfony's ItemInterface extends PSR-6's CacheItemInterface and adds tag().
    $langItem = $this->createMock(\Symfony\Contracts\Cache\ItemInterface::class);
    $langItem->method('isHit')->willReturn(false);
    $langItem->expects($this->once())->method('set')->with(['hello' => 'Hello', 'bye' => 'Bye']);
    $langItem->expects($this->once())->method('tag')->with('theme_trans_lang_eng-GB');
    $langItem->expects($this->once())->method('expiresAfter')->with(86400);

    $languagesItem = makePsrItem(false);
    $languagesItem->expects($this->once())->method('set')->with(['eng-GB']);
    $languagesItem->expects($this->once())->method('expiresAfter')->with(30 * 86400);

    $pool = $this->createMock(CacheItemPoolInterface::class);
    $pool->method('getItem')
        ->willReturnCallback(static fn (string $key) => str_ends_with($key, '__languages__') ? $languagesItem : $langItem);
    // First warm: both items are new → batched via saveDeferred + commit.
    $pool->expects($this->exactly(2))->method('saveDeferred');
    $pool->expects($this->once())->method('commit');

    $redis = new RedisTranslationCache($pool, 'pfx');
    $redis->warmLanguage('eng-GB', ['hello' => 'Hello', 'bye' => 'Bye']);
});

it('warms a known language with a single save (no manifest update)', function () {
    // Plain PSR-6 item: no tag() available, and the languages manifest already lists this
    // language → exactly one save, no commit.
    $langItem = makePsrItem(false);
    $langItem->expects($this->once())->method('set')->with(['a' => 'A']);

    $languagesItem = makePsrItem(true, ['eng-GB']);

    $pool = $this->createMock(CacheItemPoolInterface::class);
    $pool->method('getItem')
        ->willReturnCallback(static fn (string $key) => str_ends_with($key, '__languages__') ? $languagesItem : $langItem);
    $pool->expects($this->once())->method('save')->with($langItem);
    $pool->expects($this->never())->method('commit');

    $redis = new RedisTranslationCache($pool, 'pfx');
    $redis->warmLanguage('eng-GB', ['a' => 'A']);
});

it('invalidates a language via tag invalidation when the pool supports it', function () {
    $cache = new TagAwarePoolFake();

    $redis = new RedisTranslationCache($cache, 'prefix');
    $redis->invalidateLanguage('eng-GB');

    expect($cache->invalidatedTags)->toBe([['theme_trans_lang_eng-GB']]);
});

it('deletes the language item when the pool does not support tags', function () {
    $cache = $this->createMock(CacheItemPoolInterface::class);
    $cache->expects($this->once())
        ->method('deleteItem')
        ->with('prefix_eng-GB');

    $redis = new RedisTranslationCache($cache, 'prefix');
    $redis->invalidateLanguage('eng-GB');
});

it('invalidates tags for all known languages on invalidateAll when tag-aware', function () {
    $cache = new TagAwarePoolFake();
    $cache->items['prefix___languages__'] = makePsrItem(true, ['eng-GB', 'deu-DE']);

    $redis = new RedisTranslationCache($cache, 'prefix');
    $redis->invalidateAll();

    expect($cache->invalidatedTags)->toBe([['theme_trans_lang_eng-GB', 'theme_trans_lang_deu-DE']])
        ->and($cache->deletedKeys)->toContain('prefix___languages__')
        ->and($cache->cleared)->toBeFalse();
});

it('clears the pool and drops the manifest on invalidateAll without tag support', function () {
    $pool = $this->createMock(CacheItemPoolInterface::class);
    $pool->expects($this->once())->method('clear');
    $pool->expects($this->once())->method('deleteItem')->with('prefix___languages__');

    $redis = new RedisTranslationCache($pool, 'prefix');
    $redis->invalidateAll();
});
