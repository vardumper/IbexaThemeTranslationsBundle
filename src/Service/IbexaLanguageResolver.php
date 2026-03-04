<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Service;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;

final class IbexaLanguageResolver implements LanguageResolverInterface
{
    public function __construct(
        private readonly SiteAccessServiceInterface $siteAccessService,
        private readonly ConfigResolverInterface $configResolver,
    ) {
    }

    public function getUsedLanguages(): array
    {
        $languages = [];

        foreach ($this->siteAccessService->getAll() as $siteAccess) {
            try {
                $siteLanguages = $this->configResolver->getParameter('languages', null, $siteAccess->name);
                $languages = array_merge($languages, $siteLanguages);
            } catch (\Exception) { /* siteaccess has no languages configured */
            }
        }

        return array_unique($languages);
    }

    public function getCurrentLanguage(): string
    {
        $languages = $this->configResolver->getParameter('languages');

        return $languages[0] ?? 'eng-GB';
    }
}
