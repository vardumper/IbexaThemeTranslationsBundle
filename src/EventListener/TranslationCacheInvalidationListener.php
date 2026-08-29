<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use vardumper\IbexaThemeTranslationsBundle\Cache\TranslationCacheWarmer;
use vardumper\IbexaThemeTranslationsBundle\Entity\Translation;

/**
 * Invalidates and re-warms the translation caches for affected languages.
 *
 * The per-entity events only record which languages changed (no I/O). The real
 * work happens once in postFlush, deduplicated per language — so a bulk flush of
 * N rows touching L languages costs L cache re-warms instead of N, and always
 * reads the database after all SQL of the flush has been executed.
 */
#[AsEntityListener(event: Events::postPersist, entity: Translation::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Translation::class)]
#[AsEntityListener(event: Events::postRemove, entity: Translation::class)]
#[AsDoctrineListener(event: Events::postFlush)]
final class TranslationCacheInvalidationListener
{
    /**
     * Languages with changed translations during the current flush cycle.
     *
     * @var array<string, true>
     */
    private array $pendingLanguages = [];

    public function __construct(
        private readonly TranslationCacheWarmer $warmer,
    ) {
    }

    public function postPersist(Translation $translation): void
    {
        $this->queue($translation);
    }

    public function postUpdate(Translation $translation): void
    {
        $this->queue($translation);
    }

    public function postRemove(Translation $translation): void
    {
        $this->queue($translation);
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->pendingLanguages === []) {
            return;
        }

        $languages = array_keys($this->pendingLanguages);
        $this->pendingLanguages = [];

        foreach ($languages as $languageCode) {
            $this->warmer->invalidateAndWarmLanguage($languageCode);
        }
    }

    private function queue(Translation $translation): void
    {
        $languageCode = $translation->getLanguageCode();
        if ($languageCode !== null) {
            $this->pendingLanguages[$languageCode] = true;
        }
    }
}
