<?php

declare(strict_types=1);

use Doctrine\ORM\QueryBuilder;
use vardumper\IbexaThemeTranslationsBundle\Entity\TranslationDraft;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationDraftRepository;

uses(PHPUnit\Framework\TestCase::class);

function makeDraftRepo(QueryBuilder $qb): TranslationDraftRepository
{
    return new class($qb) extends TranslationDraftRepository {
        public function __construct(private QueryBuilder $qb)
        {
            // Skip parent constructor for isolated unit tests.
        }

        public function createQueryBuilder($alias, $indexBy = null): QueryBuilder
        {
            return $this->qb;
        }
    };
}

function makeDraftQb(array $result = []): QueryBuilder
{
    $query = testMock(Doctrine\ORM\AbstractQuery::class);
    $query->method('getResult')->willReturn($result);

    $qb = testMock(QueryBuilder::class);
    $qb->method('andWhere')->willReturnSelf();
    $qb->method('setParameter')->willReturnSelf();
    $qb->method('orderBy')->willReturnSelf();
    $qb->method('getQuery')->willReturn($query);

    return $qb;
}

it('findByTransKey returns query result', function () {
    $rows = [new TranslationDraft('eng-GB', 'draft.key', 'Hello')];
    $repo = makeDraftRepo(makeDraftQb($rows));

    expect($repo->findByTransKey('draft.key'))->toBe($rows);
});

it('findOneByKeyAndLanguage delegates to findOneBy criteria', function () {
    $expected = new TranslationDraft('eng-GB', 'one.key', 'Value');

    $repo = new class($expected) extends TranslationDraftRepository {
        public function __construct(private TranslationDraft $expected)
        {
        }

        public function findOneBy(array $criteria, array $orderBy = null): ?object
        {
            if (($criteria['transKey'] ?? null) === 'one.key' && ($criteria['languageCode'] ?? null) === 'eng-GB') {
                return $this->expected;
            }

            return null;
        }
    };

    expect($repo->findOneByKeyAndLanguage('one.key', 'eng-GB'))->toBe($expected);
    expect($repo->findOneByKeyAndLanguage('other', 'eng-GB'))->toBeNull();
});

it('findIndexedByTransKey returns empty array for empty input', function () {
    $repo = makeDraftRepo(makeDraftQb([]));
    expect($repo->findIndexedByTransKey([]))->toBe([]);
});

it('findIndexedByTransKey groups drafts by transKey and languageCode', function () {
    $d1 = new TranslationDraft('eng-GB', 'k1', 'A');
    $d2 = new TranslationDraft('deu-DE', 'k1', 'B');
    $d3 = new TranslationDraft('eng-GB', 'k2', 'C');

    $repo = makeDraftRepo(makeDraftQb([$d1, $d2, $d3]));
    $index = $repo->findIndexedByTransKey(['k1', 'k2']);

    expect($index)->toHaveKey('k1');
    expect($index)->toHaveKey('k2');
    expect($index['k1'])->toHaveKey('eng-GB');
    expect($index['k1'])->toHaveKey('deu-DE');
    expect($index['k2'])->toHaveKey('eng-GB');
    expect($index['k1']['eng-GB'])->toBe($d1);
    expect($index['k1']['deu-DE'])->toBe($d2);
    expect($index['k2']['eng-GB'])->toBe($d3);
});
