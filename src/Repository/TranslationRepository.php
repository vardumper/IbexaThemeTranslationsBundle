<?php

declare(strict_types=1);

namespace fork\IbexaThemeTranslationsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use fork\IbexaThemeTranslationsBundle\Entity\Translation;

/**
 * @extends ServiceEntityRepository<Translation>
 *
 * @method Translation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Translation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Translation[]    findAll()
 * @method Translation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TranslationRepository extends ServiceEntityRepository
{
    private const STATUS_VALUES = ['', 'missing', 'done', 'pending'];
    private const SORTABLE_COLUMNS = ['id', 'transKey', 'languageCode', 'translation'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Translation::class);
    }

    public function translate(string $transKey, string $languageCode): string
    {
        $res = $this->createQueryBuilder('t')
            ->select('t.translation')
            ->andWhere('t.transKey = :transKey')
            ->setParameter('transKey', $transKey)
            ->andWhere('t.languageCode = :languageCode')
            ->setParameter('languageCode', $languageCode)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        if ($res) {
            return $res[0]['translation'] ?? $transKey;
        }

        return $transKey;
    }

    /**
     * @return Translation[] Returns an array of Translation objects
     */
    public function findByTransKey($transKey): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.transKey = :transKey')
            ->setParameter('transKey', $transKey)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByLanguageCode($languageCode): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.languageCode = :languageCode')
            ->setParameter('languageCode', $languageCode)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, string> key => translation for a given language
     */
    public function findAllByLanguageCodeAsKeyValueMap(string $languageCode): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.transKey', 't.translation')
            ->andWhere('t.languageCode = :languageCode')
            ->setParameter('languageCode', $languageCode)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['transKey']] = $row['translation'] ?? '';
        }

        return $map;
    }

    public function findByStatus(string $status): array
    {
        $condition = ($status === 'missing')
            ? "t.translation = '' OR t.translation IS NULL"
            : "t.translation != '' OR t.translation IS NOT NULL";

        return $this->createQueryBuilder('t')
            ->andWhere($condition)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByFilter(string $languageCode = '', string $status = '', string $search = '', string $sortBy = 'id', string $sortDir = 'ASC'): mixed
    {
        if (!in_array($status, self::STATUS_VALUES, true)) {
            throw new Exception('Invalid status');
        }
        if (!in_array($sortBy, self::SORTABLE_COLUMNS, true)) {
            $sortBy = 'id';
        }
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        $qb = $this->createQueryBuilder('t');

        if (!empty($languageCode)) {
            $qb->andWhere('t.languageCode = :languageCode')
                ->setParameter('languageCode', $languageCode);
        }

        if ($status === 'missing') {
            $qb->andWhere("t.translation = '' OR t.translation IS NULL");
        } elseif ($status === 'done') {
            $qb->andWhere("t.translation != '' AND t.translation IS NOT NULL");
        } elseif ($status === 'pending') {
            $draftClass = 'fork\\IbexaThemeTranslationsBundle\\Entity\\TranslationDraft';
            $qb->andWhere(
                $qb->expr()->exists(
                    "SELECT d.id FROM {$draftClass} d WHERE d.transKey = t.transKey AND d.languageCode = t.languageCode"
                )
            );
        }

        if (!empty($search)) {
            $qb->andWhere('t.transKey LIKE :search OR t.translation LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->orderBy('t.' . $sortBy, $sortDir)->getQuery()->getResult();
    }

    public function findByTransKeyAndLocale(string $transKey, string $locale): ?Translation
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.transKey = :transKey')
            ->andWhere('t.languageCode = :languageCode')
            ->setParameter('transKey', $transKey)
            ->setParameter('languageCode', $locale)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return string[] Language codes that already have a row for the given key
     */
    public function findLanguageCodesForKey(string $transKey): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.languageCode')
            ->where('t.transKey = :transKey')
            ->setParameter('transKey', $transKey)
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'languageCode');
    }

    /**
     * @return string[] All distinct language codes in the database
     */
    public function findAllLanguageCodes(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('DISTINCT t.languageCode')
            ->getQuery()
            ->getResult();

        return array_column($rows, 'languageCode');
    }

    /**
     * @return string[] All distinct translation keys in the database
     */
    public function findAllUniqueKeys(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('DISTINCT t.transKey')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'transKey');
    }

    public function deleteByLanguageCode(string $languageCode): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.languageCode = :languageCode')
            ->setParameter('languageCode', $languageCode)
            ->getQuery()
            ->execute();
    }

    public function truncate(): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->getQuery()
            ->execute();
        $this->_em->getConnection()->executeQuery('ALTER TABLE `translation` AUTO_INCREMENT = 1;');
    }
}
