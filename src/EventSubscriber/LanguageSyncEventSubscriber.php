<?php

declare(strict_types=1);

namespace fork\IbexaThemeTranslationsBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use fork\IbexaThemeTranslationsBundle\Entity\Translation;
use fork\IbexaThemeTranslationsBundle\Repository\TranslationRepository;
use Ibexa\Contracts\Core\Repository\Events\Language\CreateLanguageEvent;
use Ibexa\Contracts\Core\Repository\Events\Language\DeleteLanguageEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class LanguageSyncEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TranslationRepository $translationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CreateLanguageEvent::class => 'onLanguageCreate',
            DeleteLanguageEvent::class => 'onLanguageDelete',
        ];
    }

    public function onLanguageCreate(CreateLanguageEvent $event): void
    {
        $languageCode = $event->getLanguage()->languageCode;
        $keys = $this->translationRepository->findAllUniqueKeys();

        foreach ($keys as $key) {
            $this->entityManager->persist(Translation::create($languageCode, $key));
        }

        $this->entityManager->flush();
    }

    public function onLanguageDelete(DeleteLanguageEvent $event): void
    {
        $this->translationRepository->deleteByLanguageCode($event->getLanguage()->languageCode);
    }
}
