<?php

declare(strict_types=1);

use Doctrine\Persistence\ObjectManager;
use vardumper\IbexaThemeTranslationsBundle\DataFixtures\DefaultTranslationsFixture;
use vardumper\IbexaThemeTranslationsBundle\Entity\Translation;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationRepository;

uses(PHPUnit\Framework\TestCase::class);

it('persists example.key when it does not exist yet', function () {
    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findOneBy')
        ->with(['languageCode' => 'eng-GB', 'transKey' => 'example.key'])
        ->willReturn(null);

    $manager = $this->createMock(ObjectManager::class);
    $manager->expects($this->once())
        ->method('persist')
        ->with($this->callback(fn ($entity) => $entity instanceof Translation
            && $entity->getTransKey() === 'example.key'
            && $entity->getLanguageCode() === 'eng-GB'
            && $entity->getTranslation() === 'Hello World'));
    $manager->expects($this->once())->method('flush');
    $manager->expects($this->once())->method('clear');

    $fixture = new DefaultTranslationsFixture($repo);
    $fixture->load($manager);
});

it('does not persist when the entity already exists', function () {
    $existing = new Translation('eng-GB', 'example.key', 'Hello World');

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findOneBy')
        ->with(['languageCode' => 'eng-GB', 'transKey' => 'example.key'])
        ->willReturn($existing);

    $manager = $this->createMock(ObjectManager::class);
    $manager->expects($this->never())->method('persist');
    $manager->expects($this->once())->method('flush');
    $manager->expects($this->once())->method('clear');

    $fixture = new DefaultTranslationsFixture($repo);
    $fixture->load($manager);
});
