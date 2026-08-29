<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Ibexa\Contracts\Core\Repository\Events\Language\CreateLanguageEvent;
use Ibexa\Contracts\Core\Repository\Events\Language\DeleteLanguageEvent;
use Ibexa\Contracts\Core\Repository\Values\Content\Language;
use Ibexa\Contracts\Core\Repository\Values\Content\LanguageCreateStruct;
use vardumper\IbexaThemeTranslationsBundle\Cache\TranslationCacheInterface;
use vardumper\IbexaThemeTranslationsBundle\Cache\TranslationCacheWarmer;
use vardumper\IbexaThemeTranslationsBundle\EventSubscriber\LanguageSyncEventSubscriber;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationDraftRepository;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationRepository;

uses(PHPUnit\Framework\TestCase::class);

it('subscribes to CreateLanguageEvent and DeleteLanguageEvent', function () {
    $events = LanguageSyncEventSubscriber::getSubscribedEvents();

    expect($events)->toHaveKey(CreateLanguageEvent::class)
        ->and($events)->toHaveKey(DeleteLanguageEvent::class);
});

it('creates stub translations for all existing keys when a language is created', function () {
    $language = new Language(['languageCode' => 'fra-FR']);
    $createStruct = $this->createMock(LanguageCreateStruct::class);
    $event = new CreateLanguageEvent($language, $createStruct);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findAllUniqueKeys')->willReturn(['hello', 'world']);

    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->exactly(2))->method('persist');
    $em->expects($this->once())->method('flush');

    $subscriber = new LanguageSyncEventSubscriber(
        $repo,
        testMock(TranslationDraftRepository::class),
        $em,
        makeCacheWarmer(),
    );
    $subscriber->onLanguageCreate($event);
});

it('persists nothing when no keys exist on language creation', function () {
    $language = new Language(['languageCode' => 'jpn-JP']);
    $createStruct = $this->createMock(LanguageCreateStruct::class);
    $event = new CreateLanguageEvent($language, $createStruct);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findAllUniqueKeys')->willReturn([]);

    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->never())->method('persist');
    $em->expects($this->once())->method('flush');

    $subscriber = new LanguageSyncEventSubscriber(
        $repo,
        testMock(TranslationDraftRepository::class),
        $em,
        makeCacheWarmer(),
    );
    $subscriber->onLanguageCreate($event);
});

it('deletes translations and drafts for a deleted language and invalidates its caches', function () {
    $language = new Language(['languageCode' => 'deu-DE']);
    $event = new DeleteLanguageEvent($language);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->expects($this->once())
        ->method('deleteByLanguageCode')
        ->with('deu-DE');

    $draftRepo = testMock(TranslationDraftRepository::class);
    $draftRepo->expects($this->once())
        ->method('deleteByLanguageCode')
        ->with('deu-DE');

    // Real warmer (final class) with one mocked tier to observe the invalidation.
    $cache = testMock(TranslationCacheInterface::class);
    $cache->expects($this->once())->method('invalidateLanguage')->with('deu-DE');
    $warmer = new TranslationCacheWarmer(testMock(TranslationRepository::class), [$cache]);

    $em = $this->createMock(EntityManagerInterface::class);

    $subscriber = new LanguageSyncEventSubscriber($repo, $draftRepo, $em, $warmer);
    $subscriber->onLanguageDelete($event);
});
