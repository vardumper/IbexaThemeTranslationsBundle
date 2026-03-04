<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Twig;

use Twig\Extension\RuntimeExtensionInterface;
use vardumper\IbexaThemeTranslationsBundle\Service\LanguageResolverInterface;
use vardumper\IbexaThemeTranslationsBundle\Service\TranslationServiceInterface;

final class TranslationRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly TranslationServiceInterface $translationService,
        private readonly LanguageResolverInterface $languageResolver,
        private readonly string $defaultLanguage,
    ) {
    }

    /**
     * @tutorial {{ 'key'|l10n }} translates a key into the current language
     * @tutorial {{ 'key'|l10n('deu-DE') }} translates a key into the given language
     */
    public function l10n(string $key, string $overrideLanguageCode = ''): string
    {
        if (!empty($overrideLanguageCode)) {
            return $this->translationService->translate($key, $overrideLanguageCode);
        }

        $language = $this->languageResolver->getCurrentLanguage();
        $translation = $this->translationService->translate($key, $language);

        if (empty($translation) && $language !== $this->defaultLanguage) {
            return $this->translationService->translate($key, $this->defaultLanguage);
        }

        return $translation;
    }
}
