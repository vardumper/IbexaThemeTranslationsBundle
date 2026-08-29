<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Stores the complete key => translation map of each language in a single cache item.
 *
 * - Lookups are read-only (getItem without saving), so misses are never cached:
 *   a newly added row becomes visible as soon as the language is warmed again.
 * - Warming one language is exactly one cache write, regardless of key count.
 * - invalidateAll() only touches this bundle's own keys (via tags + a small
 *   manifest of known languages) instead of clearing the whole pool.
 *
 * The pool is typed against PSR-6 because we need getItem/saveDeferred/commit;
 * Symfony cache pools implement it, and tag-aware invalidation is used when available.
 */
final class RedisTranslationCache implements TranslationCacheInterface
{
    private const TAG_PREFIX = 'theme_trans_lang_';

    /** Manifest item listing every language code ever warmed, for prefix-scoped invalidation. */
    private const LANGUAGES_KEY = '__languages__';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly string $prefix,
    ) {
    }

    public function get(string $languageCode, string $transKey): ?string
    {
        $item = $this->cache->getItem($this->getCacheKey($languageCode));
        if (!$item->isHit()) {
            return null;
        }

        $map = $item->get();
        if (!\is_array($map)) {
            return null; /* corrupt or legacy entry — treat as miss */
        }

        return $map[$transKey] ?? null;
    }

    public function warmLanguage(string $languageCode, array $translations): void
    {
        $item = $this->cache->getItem($this->getCacheKey($languageCode));
        $item->set($translations);
        if ($item instanceof \Symfony\Contracts\Cache\ItemInterface) { /* tag() is a Symfony extension of PSR-6 items */
            $item->tag(self::TAG_PREFIX . $languageCode);
        }
        $item->expiresAfter(86400);

        $languagesItem = $this->cache->getItem($this->getLanguagesKey());
        $languages = ($languagesItem->isHit() && \is_array($languagesItem->get())) ? $languagesItem->get() : [];

        if (!\in_array($languageCode, $languages, true)) {
            $languages[] = $languageCode;
            $languagesItem->set($languages);
            $languagesItem->expiresAfter(30 * 86400); /* outlives the per-language TTL so old tags stay discoverable */

            // Batch both writes into a single round-trip.
            $this->cache->saveDeferred($item);
            $this->cache->saveDeferred($languagesItem);
            $this->cache->commit();

            return;
        }

        $this->cache->save($item);
    }

    public function invalidateLanguage(string $languageCode): void
    {
        if ($this->cache instanceof TagAwareCacheInterface) {
            $this->cache->invalidateTags([self::TAG_PREFIX . $languageCode]);
        } else {
            $this->cache->deleteItem($this->getCacheKey($languageCode));
        }
    }

    public function invalidateAll(): void
    {
        if ($this->cache instanceof TagAwareCacheInterface) {
            $languagesItem = $this->cache->getItem($this->getLanguagesKey());
            $languages = ($languagesItem->isHit() && \is_array($languagesItem->get())) ? $languagesItem->get() : [];

            if ($languages !== []) {
                $this->cache->invalidateTags(\array_map(
                    static fn (string $languageCode): string => self::TAG_PREFIX . $languageCode,
                    $languages
                ));
            }
        } else {
            // No tag support: fall back to clearing the pool. This is only safe
            // because the bundle wires a dedicated cache pool for this service;
            // do not point RedisTranslationCache at a shared pool.
            $this->cache->clear();
        }

        $this->cache->deleteItem($this->getLanguagesKey());
    }

    private function getCacheKey(string $languageCode): string
    {
        return $this->prefix . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $languageCode); /* PSR-6: valid key chars are alphanumeric + _.-{} */
    }

    private function getLanguagesKey(): string
    {
        return $this->prefix . '_' . self::LANGUAGES_KEY;
    }
}
