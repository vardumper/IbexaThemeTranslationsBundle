<?php

declare(strict_types=1);

namespace fork\IbexaThemeTranslationsBundle\Cache;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class RedisTranslationCache implements TranslationCacheInterface
{
    private const TAG_PREFIX = 'theme_trans_lang_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly string $prefix,
    ) {
    }

    public function get(string $languageCode, string $transKey): ?string
    {
        $cacheKey = $this->getCacheKey($languageCode, $transKey);

        $sentinel = "\0__MISS__\0"; /* distinguishes cache miss from cached empty string */

        $value = $this->cache->get($cacheKey, function () use ($sentinel) {
            return $sentinel;
        });

        return $value === $sentinel ? null : $value;
    }

    public function warmLanguage(string $languageCode, array $translations): void
    {
        foreach ($translations as $key => $value) {
            $cacheKey = $this->getCacheKey($languageCode, $key);

            $this->cache->delete($cacheKey);

            $this->cache->get($cacheKey, function (ItemInterface $item) use ($languageCode, $value) {
                $item->tag(self::TAG_PREFIX . $languageCode);
                $item->expiresAfter(86400);

                return $value;
            });
        }
    }

    public function invalidateLanguage(string $languageCode): void
    {
        if ($this->cache instanceof TagAwareCacheInterface) {
            $this->cache->invalidateTags([self::TAG_PREFIX . $languageCode]);
        }
    }

    public function invalidateAll(): void
    {
        if (method_exists($this->cache, 'clear')) {
            $this->cache->clear();
        }
    }

    private function getCacheKey(string $languageCode, string $transKey): string
    {
        return $this->prefix . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $languageCode . '_' . $transKey); /* PSR-6: valid key chars are alphanumeric + _.-{} */
    }
}
