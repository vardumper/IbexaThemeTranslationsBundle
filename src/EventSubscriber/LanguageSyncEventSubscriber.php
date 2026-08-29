<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Ibexa\Contracts\Core\Repository\Events\Language\CreateLanguageEvent;
use Ibexa\Contracts\Core\Repository\Events\Language\DeleteLanguageEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use vardumper\IbexaThemeTranslationsBundle\Cache\TranslationCacheWarmer;
use vardumper\IbexaThemeTranslationsBundle\Entity\Translation;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationDraftRepository;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationRepository;

final class LanguageSyncEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TranslationRepository $translationRepository,
        private readonly TranslationDraftRepository $draftRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslationCacheWarmer $warmer,
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

        // Single flush; cache invalidation is batched per language in postFlush.
        $this->entityManager->flush();
    }

    public function onLanguageDelete(DeleteLanguageEvent $event): void
    {
        $languageCode = $event->getLanguage()->languageCode;

        // Bulk DQL deletes bypass entity listeners, so drop drafts explicitly and
        // invalidate the caches by hand — otherwise stale translations would be
        // served from every cache tier until a manual warmup.
        $this->draftRepository->deleteByLanguageCode($languageCode);
        $this->translationRepository->deleteByLanguageCode($languageCode);

        // Invalidate only: the language no longer exists, so there is nothing to re-warm.
        $this->warmer->invalidateLanguage($languageCode);
    }
}
