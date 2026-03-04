<?php

declare(strict_types=1);

namespace fork\IbexaThemeTranslationsBundle\Service;

interface LanguageResolverInterface
{
    /**
     * @return string[] All language codes in use (e.g., ['eng-GB', 'deu-DE', 'fra-FR'])
     */
    public function getUsedLanguages(): array;

    /**
     * @return string The current language code for the active request/session
     */
    public function getCurrentLanguage(): string;
}
