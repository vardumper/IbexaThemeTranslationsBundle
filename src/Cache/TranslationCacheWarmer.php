<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Cache;

use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationRepository;

final class TranslationCacheWarmer
{
    /**
     * @var iterable<TranslationCacheInterface>
     */
    private iterable $caches;

    public function __construct(
        private readonly TranslationRepository $repository,
        iterable $caches,
    ) {
        $this->caches = $caches;
    }

    /**
     * Warm all cache tiers for a single language.
     */
    public function warmLanguage(string $languageCode): void
    {
        $translations = $this->repository->findAllByLanguageCodeAsKeyValueMap($languageCode);

        foreach ($this->caches as $cache) {
            $cache->warmLanguage($languageCode, $translations);
        }
    }

    /**
     * Invalidate all cache tiers for a single language, then re-warm.
     */
    public function invalidateAndWarmLanguage(string $languageCode): void
    {
        foreach ($this->caches as $cache) {
            $cache->invalidateLanguage($languageCode);
        }

        $this->warmLanguage($languageCode);
    }

    /**
     * Invalidate all cache tiers for a single language without re-warming.
     * Use when the data no longer exists (e.g. the language was deleted).
     */
    public function invalidateLanguage(string $languageCode): void
    {
        foreach ($this->caches as $cache) {
            $cache->invalidateLanguage($languageCode);
        }
    }

    /**
     * Warm all cache tiers for all known languages, loading everything in one query.
     */
    public function warmAll(): void
    {
        $grouped = $this->repository->findAllGroupedByLanguage();

        foreach ($grouped as $languageCode => $translations) {
            foreach ($this->caches as $cache) {
                $cache->warmLanguage($languageCode, $translations);
            }
        }
    }

    /**
     * Clear all cache tiers.
     */
    public function clearAll(): void
    {
        foreach ($this->caches as $cache) {
            $cache->invalidateAll();
        }
    }
}
