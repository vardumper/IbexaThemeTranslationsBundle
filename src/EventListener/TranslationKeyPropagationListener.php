<?php

declare(strict_types=1);

namespace fork\IbexaThemeTranslationsBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use fork\IbexaThemeTranslationsBundle\Entity\Translation;
use fork\IbexaThemeTranslationsBundle\Repository\TranslationRepository;
use fork\IbexaThemeTranslationsBundle\Service\LanguageResolverInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postFlush)]
final class TranslationKeyPropagationListener
{
    /** @var array<string, true> Keys queued during the current flush cycle */
    private array $pendingKeys = [];

    public function __construct(
        private readonly TranslationRepository $translationRepository,
        private readonly LanguageResolverInterface $languageResolver,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Translation) {
            return;
        }

        $this->pendingKeys[$entity->getTransKey()] = true;
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (empty($this->pendingKeys)) {
            return;
        }

        $keys = array_keys($this->pendingKeys);
        $this->pendingKeys = [];

        $em = $args->getObjectManager();
        $activeLanguages = $this->languageResolver->getUsedLanguages();
        $created = false;

        foreach ($keys as $transKey) {
            $existing = array_flip($this->translationRepository->findLanguageCodesForKey($transKey));

            foreach ($activeLanguages as $languageCode) {
                if (isset($existing[$languageCode])) {
                    continue;
                }
                $em->persist(Translation::create($languageCode, $transKey));
                $created = true;
            }
        }

        if ($created) {
            $em->flush();
        }
    }
}
