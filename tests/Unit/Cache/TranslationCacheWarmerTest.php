<?php

declare(strict_types=1);

use fork\IbexaThemeTranslationsBundle\Cache\TranslationCacheInterface;
use fork\IbexaThemeTranslationsBundle\Cache\TranslationCacheWarmer;
use fork\IbexaThemeTranslationsBundle\Repository\TranslationRepository;

uses(PHPUnit\Framework\TestCase::class);

it('warmLanguage fetches translations and propagates to all cache tiers', function () {
    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findAllByLanguageCodeAsKeyValueMap')
        ->with('eng-GB')
        ->willReturn(['hello' => 'Hello']);

    $cache1 = $this->createMock(TranslationCacheInterface::class);
    $cache1->expects($this->once())->method('warmLanguage')->with('eng-GB', ['hello' => 'Hello']);

    $cache2 = $this->createMock(TranslationCacheInterface::class);
    $cache2->expects($this->once())->method('warmLanguage')->with('eng-GB', ['hello' => 'Hello']);

    $warmer = new TranslationCacheWarmer($repo, [$cache1, $cache2]);
    $warmer->warmLanguage('eng-GB');
});

it('invalidateAndWarmLanguage invalidates all tiers then re-warms', function () {
    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findAllByLanguageCodeAsKeyValueMap')->willReturn([]);

    $cache = $this->createMock(TranslationCacheInterface::class);
    $cache->expects($this->once())->method('invalidateLanguage')->with('deu-DE');
    $cache->expects($this->once())->method('warmLanguage')->with('deu-DE', []);

    $warmer = new TranslationCacheWarmer($repo, [$cache]);
    $warmer->invalidateAndWarmLanguage('deu-DE');
});

it('warmAll warms every language returned by the repository', function () {
    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findAllLanguageCodes')->willReturn(['eng-GB', 'deu-DE']);
    $repo->method('findAllByLanguageCodeAsKeyValueMap')->willReturn([]);

    $cache = $this->createMock(TranslationCacheInterface::class);
    $cache->expects($this->exactly(2))->method('warmLanguage');

    $warmer = new TranslationCacheWarmer($repo, [$cache]);
    $warmer->warmAll();
});

it('clearAll calls invalidateAll on every cache tier', function () {
    $repo = $this->createMock(TranslationRepository::class);

    $cache1 = $this->createMock(TranslationCacheInterface::class);
    $cache1->expects($this->once())->method('invalidateAll');

    $cache2 = $this->createMock(TranslationCacheInterface::class);
    $cache2->expects($this->once())->method('invalidateAll');

    $warmer = new TranslationCacheWarmer($repo, [$cache1, $cache2]);
    $warmer->clearAll();
});
