<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Service;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;

final class IbexaLanguageResolver implements LanguageResolverInterface
{
    /**
     * Per-request memo. The service is instantiated per request, so this never leaks across requests.
     * getUsedLanguages() iterates every siteaccess with a config lookup each — it must not run on
     * every form build in the same request.
     *
     * @var string[]|null
     */
    private ?array $usedLanguages = null;

    public function __construct(
        private readonly SiteAccessServiceInterface $siteAccessService,
        private readonly ConfigResolverInterface $configResolver,
    ) {
    }

    /**
     * @return string[] All language codes used by any siteaccess (memoized per request)
     */
    public function getUsedLanguages(): array
    {
        if ($this->usedLanguages !== null) {
            return $this->usedLanguages;
        }

        $languages = [];

        foreach ($this->siteAccessService->getAll() as $siteAccess) {
            try {
                $siteLanguages = $this->configResolver->getParameter('languages', null, $siteAccess->name);
                $languages = array_merge($languages, $siteLanguages);
            } catch (\Exception) { /* siteaccess has no languages configured */
            }
        }

        return $this->usedLanguages = array_values(array_unique($languages));
    }

    /**
     * Returns the primary language of the current siteaccess.
     * Note: Ibexa typically runs one siteaccess per language, so this is the language the user is
     * browsing in; if a single siteaccess lists several languages, the first one always wins here.
     */
    public function getCurrentLanguage(): string
    {
        $languages = $this->configResolver->getParameter('languages');

        return $languages[0] ?? 'eng-GB';
    }
}
