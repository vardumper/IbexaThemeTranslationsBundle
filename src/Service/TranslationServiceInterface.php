<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Service;

interface TranslationServiceInterface
{
    /**
     * Translate a key for a given language code.
     * Falls through cache tiers: Tier 1 (static PHP) -> Tier 2 (Redis) -> Tier 3 (database).
     */
    public function translate(string $transKey, string $languageCode): string;
}
