<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Service;

use vardumper\IbexaThemeTranslationsBundle\Cache\RedisTranslationCache;
use vardumper\IbexaThemeTranslationsBundle\Cache\StaticArrayTranslationCache;
use vardumper\IbexaThemeTranslationsBundle\Cache\TranslationCacheWarmer;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationRepository;

final class TranslationService implements TranslationServiceInterface
{
    public function __construct(
        private readonly StaticArrayTranslationCache $staticCache,
        private readonly RedisTranslationCache $redisCache,
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

        $value = $this->redisCache->get($languageCode, $transKey); /* tier 2: Redis */
        if ($value !== null) {
            return $value;
        }

        $value = $this->repository->translate($transKey, $languageCode); /* tier 3: DB (source of truth) */
        $this->warmer->warmLanguage($languageCode); /* warm all tiers on DB hit */

        return $value;
    }
}
