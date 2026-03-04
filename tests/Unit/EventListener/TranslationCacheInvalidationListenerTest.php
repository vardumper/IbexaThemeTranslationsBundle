<?php

declare(strict_types=1);

use vardumper\IbexaThemeTranslationsBundle\Cache\StaticArrayTranslationCache;
use vardumper\IbexaThemeTranslationsBundle\Cache\TranslationCacheWarmer;
use vardumper\IbexaThemeTranslationsBundle\Entity\Translation;
use vardumper\IbexaThemeTranslationsBundle\EventListener\TranslationCacheInvalidationListener;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationRepository;

uses(PHPUnit\Framework\TestCase::class);

afterEach(function () {
    foreach (glob(sys_get_temp_dir() . '/inv_listen_test_*') ?: [] as $dir) {
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
});

it('postPersist invalidates and re-warms the cache for the translation language', function () {
    $dir = sys_get_temp_dir() . '/inv_listen_test_' . uniqid('', true);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findAllByLanguageCodeAsKeyValueMap')
        ->with('eng-GB')->willReturn(['hello' => 'Hello v2']);
    $static = new StaticArrayTranslationCache($dir);
    $static->warmLanguage('eng-GB', ['hello' => 'Hello v1']);
    $listener = new TranslationCacheInvalidationListener(new TranslationCacheWarmer($repo, [$static]));

    $listener->postPersist(new Translation('eng-GB', 'hello', 'Hello v2'));

    expect($static->get('eng-GB', 'hello'))->toBe('Hello v2');
});

it('postUpdate invalidates and re-warms the cache for the translation language', function () {
    $dir = sys_get_temp_dir() . '/inv_listen_test_' . uniqid('', true);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findAllByLanguageCodeAsKeyValueMap')
        ->with('deu-DE')->willReturn(['gruss' => 'Hallo']);
    $static = new StaticArrayTranslationCache($dir);
    $listener = new TranslationCacheInvalidationListener(new TranslationCacheWarmer($repo, [$static]));

    $listener->postUpdate(new Translation('deu-DE', 'gruss', 'Hallo'));

    expect($static->get('deu-DE', 'gruss'))->toBe('Hallo');
});

it('postRemove invalidates and re-warms the cache for the translation language', function () {
    $dir = sys_get_temp_dir() . '/inv_listen_test_' . uniqid('', true);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findAllByLanguageCodeAsKeyValueMap')
        ->with('fra-FR')->willReturn([]);
    $static = new StaticArrayTranslationCache($dir);
    $static->warmLanguage('fra-FR', ['bonjour' => 'Hello']);
    $listener = new TranslationCacheInvalidationListener(new TranslationCacheWarmer($repo, [$static]));

    $listener->postRemove(new Translation('fra-FR', 'bonjour'));

    expect($static->get('fra-FR', 'bonjour'))->toBeNull();
});

it('does not warm when the translation has no language code', function () {
    $dir = sys_get_temp_dir() . '/inv_listen_test_' . uniqid('', true);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->expects($this->never())->method('findAllByLanguageCodeAsKeyValueMap');
    $static = new StaticArrayTranslationCache($dir);
    $warmer = new TranslationCacheWarmer($repo, [$static]);
    $listener = new TranslationCacheInvalidationListener($warmer);

    $translation = new Translation('tmp', 'key');
    $ref = new ReflectionProperty(Translation::class, 'languageCode');
    $ref->setValue($translation, null);

    $listener->postPersist($translation);
});
