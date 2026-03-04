<?php

declare(strict_types=1);

namespace fork\IbexaThemeTranslationsBundle\Cache;

interface TranslationCacheInterface
{
    /**
     * Get a single translation from this cache tier.
     * Returns null on cache miss.
     */
    public function get(string $languageCode, string $transKey): ?string;

    /**
     * Warm the entire language into this cache tier.
     *
     * @param array<string, string> $translations key => value map
     */
    public function warmLanguage(string $languageCode, array $translations): void;

    /**
     * Invalidate all cached data for a given language.
     */
    public function invalidateLanguage(string $languageCode): void;

    /**
     * Invalidate all cached data for all languages.
     */
    public function invalidateAll(): void;
}
