<?php

declare(strict_types=1);

use vardumper\IbexaThemeTranslationsBundle\Cache\TranslationCacheInterface;
use vardumper\IbexaThemeTranslationsBundle\Cache\TranslationCacheWarmer;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationRepository;

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

it('warmAll warms every language returned by the repository in one query', function () {
    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findAllGroupedByLanguage')->willReturn([
        'eng-GB' => ['hello' => 'Hello'],
        'deu-DE' => [],
    ]);

    $calls = [];
    $cache = $this->createMock(TranslationCacheInterface::class);
    $cache->method('warmLanguage')
        ->willReturnCallback(static function (string $languageCode, array $translations) use (&$calls): void {
            $calls[] = [$languageCode, $translations];
        });

    $warmer = new TranslationCacheWarmer($repo, [$cache]);
    $warmer->warmAll();

    expect($calls)->toBe([
        ['eng-GB', ['hello' => 'Hello']],
        ['deu-DE', []],
    ]);
});

it('invalidateLanguage invalidates all tiers without re-warming', function () {
    $repo = $this->createMock(TranslationRepository::class);
    $repo->expects($this->never())->method('findAllByLanguageCodeAsKeyValueMap');

    $cache = $this->createMock(TranslationCacheInterface::class);
    $cache->expects($this->once())->method('invalidateLanguage')->with('deu-DE');
    $cache->expects($this->never())->method('warmLanguage');

    $warmer = new TranslationCacheWarmer($repo, [$cache]);
    $warmer->invalidateLanguage('deu-DE');
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
