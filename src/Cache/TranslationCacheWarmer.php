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
     * Warm all cache tiers for all known languages.
     */
    public function warmAll(): void
    {
        $languageCodes = $this->repository->findAllLanguageCodes();

        foreach ($languageCodes as $languageCode) {
            $this->warmLanguage($languageCode);
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
