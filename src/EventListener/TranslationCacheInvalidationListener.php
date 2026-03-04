<?php

declare(strict_types=1);

namespace fork\IbexaThemeTranslationsBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use fork\IbexaThemeTranslationsBundle\Cache\TranslationCacheWarmer;
use fork\IbexaThemeTranslationsBundle\Entity\Translation;

#[AsEntityListener(event: Events::postPersist, entity: Translation::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Translation::class)]
#[AsEntityListener(event: Events::postRemove, entity: Translation::class)]
final class TranslationCacheInvalidationListener
{
    public function __construct(
        private readonly TranslationCacheWarmer $warmer,
    ) {
    }

    public function postPersist(Translation $translation): void
    {
        $this->invalidate($translation);
    }

    public function postUpdate(Translation $translation): void
    {
        $this->invalidate($translation);
    }

    public function postRemove(Translation $translation): void
    {
        $this->invalidate($translation);
    }

    private function invalidate(Translation $translation): void
    {
        $languageCode = $translation->getLanguageCode();
        if ($languageCode !== null) {
            $this->warmer->invalidateAndWarmLanguage($languageCode);
        }
    }
}
