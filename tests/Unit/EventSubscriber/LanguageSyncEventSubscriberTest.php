<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use fork\IbexaThemeTranslationsBundle\EventSubscriber\LanguageSyncEventSubscriber;
use fork\IbexaThemeTranslationsBundle\Repository\TranslationRepository;
use Ibexa\Contracts\Core\Repository\Events\Language\CreateLanguageEvent;
use Ibexa\Contracts\Core\Repository\Events\Language\DeleteLanguageEvent;
use Ibexa\Contracts\Core\Repository\Values\Content\Language;
use Ibexa\Contracts\Core\Repository\Values\Content\LanguageCreateStruct;

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

    $subscriber = new LanguageSyncEventSubscriber($repo, $em);
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

    $subscriber = new LanguageSyncEventSubscriber($repo, $em);
    $subscriber->onLanguageCreate($event);
});

it('deletes all translations for a deleted language', function () {
    $language = new Language(['languageCode' => 'deu-DE']);
    $event = new DeleteLanguageEvent($language);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->expects($this->once())
        ->method('deleteByLanguageCode')
        ->with('deu-DE');

    $em = $this->createMock(EntityManagerInterface::class);

    $subscriber = new LanguageSyncEventSubscriber($repo, $em);
    $subscriber->onLanguageDelete($event);
});
