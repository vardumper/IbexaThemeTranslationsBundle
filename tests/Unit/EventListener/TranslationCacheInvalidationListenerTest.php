<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
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

it('postPersist queues the language and postFlush invalidates + re-warms it', function () {
    $dir = sys_get_temp_dir() . '/inv_listen_test_' . uniqid('', true);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findAllByLanguageCodeAsKeyValueMap')
        ->with('eng-GB')->willReturn(['hello' => 'Hello v2']);
    $static = new StaticArrayTranslationCache($dir);
    $static->warmLanguage('eng-GB', ['hello' => 'Hello v1']);
    $listener = new TranslationCacheInvalidationListener(new TranslationCacheWarmer($repo, [$static]));

    // The entity event only records the language — no cache I/O yet.
    $listener->postPersist(new Translation('eng-GB', 'hello', 'Hello v2'));
    expect($static->get('eng-GB', 'hello'))->toBe('Hello v1');

    $em = $this->createMock(EntityManagerInterface::class);
    $listener->postFlush(new PostFlushEventArgs($em));

    expect($static->get('eng-GB', 'hello'))->toBe('Hello v2');
});

it('postUpdate queues the language and postFlush invalidates + re-warms it', function () {
    $dir = sys_get_temp_dir() . '/inv_listen_test_' . uniqid('', true);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findAllByLanguageCodeAsKeyValueMap')
        ->with('deu-DE')->willReturn(['gruss' => 'Hallo']);
    $static = new StaticArrayTranslationCache($dir);
    $listener = new TranslationCacheInvalidationListener(new TranslationCacheWarmer($repo, [$static]));

    $listener->postUpdate(new Translation('deu-DE', 'gruss', 'Hallo'));

    $em = $this->createMock(EntityManagerInterface::class);
    $listener->postFlush(new PostFlushEventArgs($em));

    expect($static->get('deu-DE', 'gruss'))->toBe('Hallo');
});

it('postRemove queues the language and postFlush re-warms it without the removed row', function () {
    $dir = sys_get_temp_dir() . '/inv_listen_test_' . uniqid('', true);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findAllByLanguageCodeAsKeyValueMap')
        ->with('fra-FR')->willReturn([]);
    $static = new StaticArrayTranslationCache($dir);
    $static->warmLanguage('fra-FR', ['bonjour' => 'Hello']);
    $listener = new TranslationCacheInvalidationListener(new TranslationCacheWarmer($repo, [$static]));

    $listener->postRemove(new Translation('fra-FR', 'bonjour'));

    $em = $this->createMock(EntityManagerInterface::class);
    $listener->postFlush(new PostFlushEventArgs($em));

    expect($static->get('fra-FR', 'bonjour'))->toBeNull();
});

it('deduplicates languages so a bulk flush re-warms each language only once', function () {
    $dir = sys_get_temp_dir() . '/inv_listen_test_' . uniqid('', true);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->expects($this->once())
        ->method('findAllByLanguageCodeAsKeyValueMap')
        ->with('eng-GB')->willReturn([]);
    $static = new StaticArrayTranslationCache($dir);
    $listener = new TranslationCacheInvalidationListener(new TranslationCacheWarmer($repo, [$static]));

    // Two rows of the same language in one flush cycle.
    $listener->postPersist(new Translation('eng-GB', 'a', 'A'));
    $listener->postUpdate(new Translation('eng-GB', 'b', 'B'));

    $em = $this->createMock(EntityManagerInterface::class);
    $listener->postFlush(new PostFlushEventArgs($em));
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

    // postFlush with an empty queue is a no-op.
    $em = $this->createMock(EntityManagerInterface::class);
    $listener->postFlush(new PostFlushEventArgs($em));
});
