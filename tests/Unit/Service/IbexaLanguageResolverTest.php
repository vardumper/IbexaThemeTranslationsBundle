<?php

declare(strict_types=1);

use fork\IbexaThemeTranslationsBundle\Service\IbexaLanguageResolver;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\SiteAccess;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;

uses(PHPUnit\Framework\TestCase::class);

it('returns unique languages across all site accesses', function () {
    $siteAccessService = $this->createMock(SiteAccessServiceInterface::class);
    $configResolver = $this->createMock(ConfigResolverInterface::class);

    $sa1 = new SiteAccess('en');
    $sa2 = new SiteAccess('de');
    $sa3 = new SiteAccess('fr');

    $siteAccessService->method('getAll')->willReturn([$sa1, $sa2, $sa3]);

    $configResolver->method('getParameter')
        ->willReturnMap([
            ['languages', null, 'en', ['eng-GB', 'ger-DE']],
            ['languages', null, 'de', ['ger-DE']],
            ['languages', null, 'fr', ['fre-FR', 'eng-GB']],
        ]);

    $resolver = new IbexaLanguageResolver($siteAccessService, $configResolver);
    $result = $resolver->getUsedLanguages();

    expect($result)->toContain('eng-GB')
        ->and($result)->toContain('ger-DE')
        ->and($result)->toContain('fre-FR')
        ->and(array_count_values($result)['eng-GB'])->toBe(1)
        ->and(array_count_values($result)['ger-DE'])->toBe(1);
});

it('skips site accesses with no languages configured', function () {
    $siteAccessService = $this->createMock(SiteAccessServiceInterface::class);
    $configResolver = $this->createMock(ConfigResolverInterface::class);

    $sa1 = new SiteAccess('en');
    $sa2 = new SiteAccess('broken');

    $siteAccessService->method('getAll')->willReturn([$sa1, $sa2]);

    $configResolver->method('getParameter')
        ->willReturnCallback(function (string $paramName, $namespace, ?string $scope = null) {
            if ($scope === 'en') {
                return ['eng-GB'];
            }
            throw new \RuntimeException('No languages configured');
        });

    $resolver = new IbexaLanguageResolver($siteAccessService, $configResolver);
    $result = $resolver->getUsedLanguages();

    expect($result)->toBe(['eng-GB']);
});

it('returns empty array when no site access has languages', function () {
    $siteAccessService = $this->createMock(SiteAccessServiceInterface::class);
    $configResolver = $this->createMock(ConfigResolverInterface::class);

    $siteAccessService->method('getAll')->willReturn([new SiteAccess('default')]);
    $configResolver->method('getParameter')->willThrowException(new \RuntimeException('No languages'));

    $resolver = new IbexaLanguageResolver($siteAccessService, $configResolver);
    $result = $resolver->getUsedLanguages();

    expect($result)->toBe([]);
});

it('returns the first language as the current language', function () {
    $siteAccessService = $this->createMock(SiteAccessServiceInterface::class);
    $configResolver = $this->createMock(ConfigResolverInterface::class);

    $configResolver->method('getParameter')
        ->with('languages')
        ->willReturn(['ger-DE', 'eng-GB']);

    $resolver = new IbexaLanguageResolver($siteAccessService, $configResolver);

    expect($resolver->getCurrentLanguage())->toBe('ger-DE');
});

it('returns eng-GB as default when language list is empty', function () {
    $siteAccessService = $this->createMock(SiteAccessServiceInterface::class);
    $configResolver = $this->createMock(ConfigResolverInterface::class);

    $configResolver->method('getParameter')
        ->with('languages')
        ->willReturn([]);

    $resolver = new IbexaLanguageResolver($siteAccessService, $configResolver);

    expect($resolver->getCurrentLanguage())->toBe('eng-GB');
});
