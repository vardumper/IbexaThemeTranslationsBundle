<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use vardumper\IbexaThemeTranslationsBundle\Entity\Translation;
use vardumper\IbexaThemeTranslationsBundle\Entity\TranslationDraft;

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

    /**
     * Returns the stored translation for a key, or null when no row exists.
     * Callers can therefore distinguish "found" from "missing".
     */
    public function translateOrNull(string $transKey, string $languageCode): ?string
    {
        $res = $this->createQueryBuilder('t')
            ->select('t.translation')
            ->andWhere('t.transKey = :transKey')
            ->setParameter('transKey', $transKey)
            ->andWhere('t.languageCode = :languageCode')
            ->setParameter('languageCode', $languageCode)
            ->setMaxResults(1)
            ->getQuery()
            ->getScalarResult();

        if ($res === []) {
            return null;
        }

        return $res[0]['translation'] ?? '';
    }

    /**
     * Returns the stored translation for a key, falling back to the key itself when missing.
     */
    public function translate(string $transKey, string $languageCode): string
    {
        return $this->translateOrNull($transKey, $languageCode) ?? $transKey;
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

    /**
     * Returns a map of [transKey => languageCode[]] for all given keys in a single query.
     *
     * @param string[] $transKeys
     * @return array<string, string[]>
     */
    public function findLanguageCodesForKeys(array $transKeys): array
    {
        if ($transKeys === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('t')
            ->select('t.transKey', 't.languageCode')
            ->where('t.transKey IN (:keys)')
            ->setParameter('keys', array_values(array_unique($transKeys)))
            ->getQuery()
            ->getScalarResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['transKey']][] = $row['languageCode'];
        }

        return $map;
    }

    /**
     * Builds a query with the given filter/sort conditions applied.
     * Exposed so callers can paginate at the SQL level (e.g. Pagerfanta's ORM adapter).
     */
    public function createFilteredQueryBuilder(string $languageCode = '', string $status = '', string $search = '', string $sortBy = 'id', string $sortDir = 'ASC'): QueryBuilder
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
            $draftClass = TranslationDraft::class;
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

        return $qb->orderBy('t.' . $sortBy, $sortDir);
    }

    /**
     * @return Translation[] All rows matching the filter (no pagination)
     */
    public function findByFilter(string $languageCode = '', string $status = '', string $search = '', string $sortBy = 'id', string $sortDir = 'ASC'): array
    {
        return $this->createFilteredQueryBuilder($languageCode, $status, $search, $sortBy, $sortDir)
            ->getQuery()
            ->getResult();
    }

    /**
     * Number of rows matching the filter (SQL COUNT — no entity hydration).
     *
     * The ORDER BY added by createFilteredQueryBuilder() is cleared before counting: a
     * COUNT(*) query that still carries "ORDER BY t.id" on a non-aggregated column is
     * rejected by PostgreSQL with SQLSTATE[42803] (GROUP BY error), so the ordering must
     * be dropped for aggregate queries. Ordering is irrelevant to a row count anyway.
     */
    public function countByFilter(string $languageCode = '', string $status = '', string $search = '', string $sortBy = 'id', string $sortDir = 'ASC'): int
    {
        return (int) $this->createFilteredQueryBuilder($languageCode, $status, $search, $sortBy, $sortDir)
            ->resetDQLPart('orderBy')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * One page of rows matching the filter (SQL LIMIT/OFFSET — no full-table load).
     *
     * @return Translation[]
     */
    public function findPagedByFilter(string $languageCode, string $status, string $search, string $sortBy, string $sortDir, int $firstResult, int $maxResults): array
    {
        return $this->createFilteredQueryBuilder($languageCode, $status, $search, $sortBy, $sortDir)
            ->setFirstResult($firstResult)
            ->setMaxResults($maxResults)
            ->getQuery()
            ->getResult();
    }

    /**
     * Scalar query for streaming CSV export (id, transKey, languageCode, translation).
     */
    public function createExportQuery(): Query
    {
        return $this->createQueryBuilder('t')
            ->select('t.id', 't.transKey', 't.languageCode', 't.translation')
            ->orderBy('t.id', 'ASC')
            ->getQuery();
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
     * Loads all existing rows for the given key/language combinations in a single query.
     * Used by bulk imports to avoid one lookup per CSV row.
     *
     * @param string[] $transKeys
     * @param string[] $languageCodes
     * @return Translation[]
     */
    public function findByTransKeysAndLanguages(array $transKeys, array $languageCodes): array
    {
        if ($transKeys === [] || $languageCodes === []) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->where('t.transKey IN (:keys)')
            ->setParameter('keys', array_values($transKeys))
            ->andWhere('t.languageCode IN (:languages)')
            ->setParameter('languages', array_values($languageCodes))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return string[] All distinct language codes in the database
     */
    public function findAllLanguageCodes(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('DISTINCT t.languageCode')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'languageCode');
    }

    /**
     * Loads all translations grouped by language in a single query.
     *
     * @return array<string, array<string, string>> languageCode => (transKey => translation)
     */
    public function findAllGroupedByLanguage(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.languageCode', 't.transKey', 't.translation')
            ->getQuery()
            ->getScalarResult();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['languageCode']][$row['transKey']] = $row['translation'] ?? '';
        }

        return $grouped;
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

    /**
     * Deletes all translations. Portable across database platforms — the previous MySQL-only
     * "reset AUTO_INCREMENT" step was removed (it broke on PostgreSQL/SQLite and only affected
     * cosmetic id values). Note: bulk delete bypasses entity listeners; callers are responsible
     * for cache invalidation (see TranslationsController::importAction).
     */
    public function truncate(): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->getQuery()
            ->execute();
    }
}
