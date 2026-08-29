<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Service;

use vardumper\IbexaThemeTranslationsBundle\Cache\RedisTranslationCache;
use vardumper\IbexaThemeTranslationsBundle\Cache\StaticArrayTranslationCache;
use vardumper\IbexaThemeTranslationsBundle\Cache\TranslationCacheWarmer;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationRepository;

final class TranslationService implements TranslationServiceInterface
{
    /**
     * Keys already known to be missing from the database during this request.
     * Prevents repeated DB lookups and cache re-warms for the same missing key.
     *
     * @var array<string, true>
     */
    private array $knownMissing = [];

    /**
     * Languages whose caches were already (re)warmed during this request.
     *
     * @var array<string, true>
     */
    private array $warmedLanguages = [];

    public function __construct(
        private readonly StaticArrayTranslationCache $staticCache,
        private readonly ?RedisTranslationCache $redisCache,
        private readonly TranslationRepository $repository,
        private readonly TranslationCacheWarmer $warmer,
    ) {
    }

    public function translate(string $transKey, string $languageCode): string
    {
        $value = $this->staticCache->get($languageCode, $transKey); /* tier 1: static PHP array (opcache-backed) */
        if ($value !== null) {
            return $value;
        }

        if ($this->redisCache !== null) {
            $value = $this->redisCache->get($languageCode, $transKey); /* tier 2: Redis */
            if ($value !== null) {
                return $value;
            }
        }

        // A key already proven missing in this request costs nothing more.
        $missId = $languageCode . "\0" . $transKey;
        if (isset($this->knownMissing[$missId])) {
            return $transKey;
        }

        $value = $this->repository->translateOrNull($transKey, $languageCode); /* tier 3: DB (source of truth) */

        if ($value === null) {
            // No row exists. Remember it for this request and do NOT re-warm the
            // caches — warming only ever writes existing rows, so a missing key
            // would miss again on every subsequent request otherwise.
            $this->knownMissing[$missId] = true;

            return $transKey;
        }

        if (!isset($this->warmedLanguages[$languageCode])) {
            $this->warmedLanguages[$languageCode] = true;
            $this->warmer->warmLanguage($languageCode); /* warm all tiers once per language per request, on a real hit */
        }

        return $value;
    }
}
