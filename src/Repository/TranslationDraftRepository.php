<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use vardumper\IbexaThemeTranslationsBundle\Entity\TranslationDraft;

/**
 * @extends ServiceEntityRepository<TranslationDraft>
 *
 * @method TranslationDraft|null find($id, $lockMode = null, $lockVersion = null)
 * @method TranslationDraft|null findOneBy(array $criteria, array $orderBy = null)
 * @method TranslationDraft[]    findAll()
 * @method TranslationDraft[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TranslationDraftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TranslationDraft::class);
    }

    public function findByTransKey(string $transKey): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.transKey = :transKey')
            ->setParameter('transKey', $transKey)
            ->orderBy('d.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByKeyAndLanguage(string $transKey, string $languageCode): ?TranslationDraft
    {
        return $this->findOneBy([
            'transKey' => $transKey,
            'languageCode' => $languageCode,
        ]);
    }

    /**
     * Bulk-deletes all drafts for a language. Bypasses entity listeners on purpose —
     * callers are responsible for any required cache invalidation.
     */
    public function deleteByLanguageCode(string $languageCode): void
    {
        $this->createQueryBuilder('d')
            ->delete()
            ->where('d.languageCode = :languageCode')
            ->setParameter('languageCode', $languageCode)
            ->getQuery()
            ->execute();
    }

    /**
     * Returns a map of [transKey => [languageCode => TranslationDraft]] for a set of keys.
     * Used to efficiently look up drafts for an entire list page in one query.
     *
     * @param string[] $transKeys
     * @return array<string, array<string, TranslationDraft>> keyed by transKey, then languageCode
     */
    public function findIndexedByTransKey(array $transKeys): array
    {
        if (empty($transKeys)) {
            return [];
        }

        $drafts = $this->createQueryBuilder('d')
            ->andWhere('d.transKey IN (:keys)')
            ->setParameter('keys', $transKeys)
            ->getQuery()
            ->getResult();

        $index = [];
        foreach ($drafts as $draft) {
            $index[$draft->getTransKey()][$draft->getLanguageCode()] = $draft;
        }

        return $index;
    }
}
