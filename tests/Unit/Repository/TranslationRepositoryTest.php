<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use vardumper\IbexaThemeTranslationsBundle\Entity\Translation;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationRepository;

uses(PHPUnit\Framework\TestCase::class);

function makeTranslationRepo(QueryBuilder $qb): TranslationRepository
{
    return new class($qb) extends TranslationRepository {
        public function __construct(private QueryBuilder $qb)
        {
            // Intentionally skip parent constructor for pure unit testing.
        }

        public function createQueryBuilder($alias, $indexBy = null): QueryBuilder
        {
            return $this->qb;
        }
    };
}

function makeQbWithQuery(array $result = [], array $scalarResult = [], mixed $oneOrNull = null, int $execute = 1, mixed $singleScalar = 0): array
{
    $query = testMock(Doctrine\ORM\Query::class);
    $query->method('getResult')->willReturn($result);
    $query->method('getScalarResult')->willReturn($scalarResult);
    $query->method('getOneOrNullResult')->willReturn($oneOrNull);
    $query->method('execute')->willReturn($execute);
    $query->method('getSingleScalarResult')->willReturn($singleScalar);

    $expr = testMock(Expr::class);
    $expr->method('exists')->willReturn('EXISTS_SUBQUERY');

    $qb = testMock(QueryBuilder::class);
    $qb->method('select')->willReturnSelf();
    $qb->method('andWhere')->willReturnSelf();
    $qb->method('where')->willReturnSelf();
    $qb->method('setParameter')->willReturnSelf();
    $qb->method('setMaxResults')->willReturnSelf();
    $qb->method('setFirstResult')->willReturnSelf();
    $qb->method('orderBy')->willReturnSelf();
    // countByFilter() clears the ORDER BY before counting so PostgreSQL accepts the COUNT query.
    $qb->method('resetDQLPart')->willReturnSelf();
    $qb->method('delete')->willReturnSelf();
    $qb->method('getQuery')->willReturn($query);
    $qb->method('expr')->willReturn($expr);

    return [$qb, $query, $expr];
}

it('translate returns translation from query result', function () {
    [$qb] = makeQbWithQuery([], [['translation' => 'Hello']]);
    $repo = makeTranslationRepo($qb);

    expect($repo->translate('hello.key', 'eng-GB'))->toBe('Hello');
});

it('translateOrNull returns the stored translation or null when no row exists', function () {
    [$qb] = makeQbWithQuery([], [['translation' => 'Hello']]);
    $repo = makeTranslationRepo($qb);
    expect($repo->translateOrNull('hello.key', 'eng-GB'))->toBe('Hello');

    [$qb2] = makeQbWithQuery();
    $repo2 = makeTranslationRepo($qb2);
    expect($repo2->translateOrNull('missing.key', 'eng-GB'))->toBeNull();
});

it('translate falls back to transKey when no row exists', function () {
    [$qb] = makeQbWithQuery([]);
    $repo = makeTranslationRepo($qb);

    expect($repo->translate('missing.key', 'eng-GB'))->toBe('missing.key');
});

it('translate falls back to transKey when translation field missing', function () {
    [$qb] = makeQbWithQuery([[]]);
    $repo = makeTranslationRepo($qb);

    expect($repo->translate('fallback.key', 'eng-GB'))->toBe('fallback.key');
});

it('findByTransKey returns query result', function () {
    $rows = [new Translation('eng-GB', 'k', 'A')];
    [$qb] = makeQbWithQuery($rows);
    $repo = makeTranslationRepo($qb);

    expect($repo->findByTransKey('k'))->toBe($rows);
});

it('findByLanguageCode returns query result', function () {
    $rows = [new Translation('eng-GB', 'k', 'A')];
    [$qb] = makeQbWithQuery($rows);
    $repo = makeTranslationRepo($qb);

    expect($repo->findByLanguageCode('eng-GB'))->toBe($rows);
});

it('findAllByLanguageCodeAsKeyValueMap maps null values to empty strings', function () {
    [$qb] = makeQbWithQuery([
        ['transKey' => 'a', 'translation' => 'A'],
        ['transKey' => 'b', 'translation' => null],
    ]);
    $repo = makeTranslationRepo($qb);

    expect($repo->findAllByLanguageCodeAsKeyValueMap('eng-GB'))->toBe([
        'a' => 'A',
        'b' => '',
    ]);
});

it('findByFilter throws on invalid status', function () {
    [$qb] = makeQbWithQuery([]);
    $repo = makeTranslationRepo($qb);

    expect(fn() => $repo->findByFilter(status: 'bad-status'))->toThrow(Exception::class, 'Invalid status');
});

it('findByFilter falls back to id and ASC for invalid sort params', function () {
    [$qb] = makeQbWithQuery([]);
    $qb->expects(testMock(PHPUnit\Framework\TestCase::class)->once())
        ->method('orderBy')
        ->with('t.id', 'ASC')
        ->willReturnSelf();

    $repo = makeTranslationRepo($qb);
    $repo->findByFilter(sortBy: 'bad', sortDir: 'bad');

    expect(true)->toBeTrue();
});

it('findByFilter applies language filter when provided', function () {
    [$qb] = makeQbWithQuery([]);
    $qb->expects(testMock(PHPUnit\Framework\TestCase::class)->once())
        ->method('setParameter')
        ->with('languageCode', 'eng-GB')
        ->willReturnSelf();

    $repo = makeTranslationRepo($qb);
    $repo->findByFilter(languageCode: 'eng-GB');

    expect(true)->toBeTrue();
});

it('findByFilter applies missing-status condition', function () {
    [$qb] = makeQbWithQuery([]);
    $qb->expects(testMock(PHPUnit\Framework\TestCase::class)->atLeastOnce())
        ->method('andWhere')
        ->with("t.translation = '' OR t.translation IS NULL")
        ->willReturnSelf();

    $repo = makeTranslationRepo($qb);
    $repo->findByFilter(status: 'missing');

    expect(true)->toBeTrue();
});

it('findByFilter applies done-status condition', function () {
    [$qb] = makeQbWithQuery([]);
    $qb->expects(testMock(PHPUnit\Framework\TestCase::class)->atLeastOnce())
        ->method('andWhere')
        ->with("t.translation != '' AND t.translation IS NOT NULL")
        ->willReturnSelf();

    $repo = makeTranslationRepo($qb);
    $repo->findByFilter(status: 'done');

    expect(true)->toBeTrue();
});

it('findByFilter pending uses an exists subquery', function () {
    [$qb] = makeQbWithQuery([]);
    $qb->expects(testMock(PHPUnit\Framework\TestCase::class)->atLeastOnce())
        ->method('andWhere')
        ->with('EXISTS_SUBQUERY')
        ->willReturnSelf();

    $repo = makeTranslationRepo($qb);
    $repo->findByFilter(status: 'pending');

    expect(true)->toBeTrue();
});

it('findByFilter applies search term and wildcard parameter', function () {
    [$qb] = makeQbWithQuery([]);
    $qb->expects(testMock(PHPUnit\Framework\TestCase::class)->atLeastOnce())
        ->method('setParameter')
        ->with('search', '%needle%')
        ->willReturnSelf();

    $repo = makeTranslationRepo($qb);
    $repo->findByFilter(search: 'needle');

    expect(true)->toBeTrue();
});

it('findByTransKeyAndLocale returns one or null result', function () {
    $entity = new Translation('eng-GB', 'k', 'v');
    [$qb] = makeQbWithQuery([], [], $entity);
    $repo = makeTranslationRepo($qb);

    expect($repo->findByTransKeyAndLocale('k', 'eng-GB'))->toBe($entity);
});

it('findLanguageCodesForKey returns scalar language codes', function () {
    [$qb] = makeQbWithQuery([], [
        ['languageCode' => 'eng-GB'],
        ['languageCode' => 'deu-DE'],
    ]);
    $repo = makeTranslationRepo($qb);

    expect($repo->findLanguageCodesForKey('k'))->toBe(['eng-GB', 'deu-DE']);
});

it('findAllLanguageCodes returns distinct language codes', function () {
    [$qb] = makeQbWithQuery([], [
        ['languageCode' => 'eng-GB'],
        ['languageCode' => 'deu-DE'],
    ]);
    $repo = makeTranslationRepo($qb);

    expect($repo->findAllLanguageCodes())->toBe(['eng-GB', 'deu-DE']);
});

it('findAllUniqueKeys returns distinct trans keys', function () {
    [$qb] = makeQbWithQuery([], [
        ['transKey' => 'a'],
        ['transKey' => 'b'],
    ]);
    $repo = makeTranslationRepo($qb);

    expect($repo->findAllUniqueKeys())->toBe(['a', 'b']);
});

it('deleteByLanguageCode executes delete query', function () {
    [$qb, $query] = makeQbWithQuery([], [], null, 1);
    $query->expects(testMock(PHPUnit\Framework\TestCase::class)->once())->method('execute')->willReturn(1);

    $repo = makeTranslationRepo($qb);
    $repo->deleteByLanguageCode('eng-GB');

    expect(true)->toBeTrue();
});

it('truncate executes the portable DQL delete (no platform-specific auto-increment reset)', function () {
    [$qb, $query] = makeQbWithQuery([], [], null, 1);
    $query->expects(testMock(PHPUnit\Framework\TestCase::class)->once())->method('execute')->willReturn(1);

    // The old MySQL-only "ALTER TABLE ... AUTO_INCREMENT" step is gone: truncate must not
    // touch the connection at all.
    $em = testMock(EntityManagerInterface::class);
    $conn = testMock(Doctrine\DBAL\Connection::class);
    $conn->expects(testMock(PHPUnit\Framework\TestCase::class)->never())->method('executeQuery');
    $em->method('getConnection')->willReturn($conn);

    $repo = makeTranslationRepo($qb);

    $ref = new ReflectionObject($repo);
    $prop = $ref->getProperty('_em');
    $prop->setAccessible(true);
    $prop->setValue($repo, $em);

    $repo->truncate();

    expect(true)->toBeTrue();
});

it('findLanguageCodesForKeys maps each key to its language codes in one query', function () {
    [$qb] = makeQbWithQuery([], [
        ['transKey' => 'a', 'languageCode' => 'eng-GB'],
        ['transKey' => 'a', 'languageCode' => 'deu-DE'],
        ['transKey' => 'b', 'languageCode' => 'fra-FR'],
    ]);
    $repo = makeTranslationRepo($qb);

    expect($repo->findLanguageCodesForKeys(['a', 'b']))->toBe([
        'a' => ['eng-GB', 'deu-DE'],
        'b' => ['fra-FR'],
    ]);
});

it('findLanguageCodesForKeys returns an empty map without querying for no keys', function () {
    [$qb, $query] = makeQbWithQuery();
    $query->expects(testMock(PHPUnit\Framework\TestCase::class)->never())->method('getScalarResult');
    $repo = makeTranslationRepo($qb);

    expect($repo->findLanguageCodesForKeys([]))->toBe([]);
});

it('findAllGroupedByLanguage groups rows by language', function () {
    [$qb] = makeQbWithQuery([], [
        ['languageCode' => 'eng-GB', 'transKey' => 'a', 'translation' => 'A'],
        ['languageCode' => 'eng-GB', 'transKey' => 'b', 'translation' => null],
        ['languageCode' => 'deu-DE', 'transKey' => 'a', 'translation' => 'Ä'],
    ]);
    $repo = makeTranslationRepo($qb);

    expect($repo->findAllGroupedByLanguage())->toBe([
        'eng-GB' => ['a' => 'A', 'b' => ''],
        'deu-DE' => ['a' => 'Ä'],
    ]);
});

it('countByFilter returns the scalar count as an int', function () {
    [$qb] = makeQbWithQuery([], [], null, 1, 42);
    $repo = makeTranslationRepo($qb);

    expect($repo->countByFilter())->toBe(42);
});

it('countByFilter strips the ORDER BY before counting (PostgreSQL GROUP BY #42803)', function () {
    [$qb] = makeQbWithQuery([], [], null, 1, 42);
    // createFilteredQueryBuilder() always adds an ORDER BY; the COUNT must drop it so that
    // "SELECT COUNT(t.id) ... ORDER BY t.id ASC" never reaches PostgreSQL (SQLSTATE[42803]).
    $qb->expects(testMock(PHPUnit\Framework\TestCase::class)->once())
        ->method('resetDQLPart')
        ->with('orderBy');

    $repo = makeTranslationRepo($qb);

    expect($repo->countByFilter())->toBe(42);
});

it('findPagedByFilter applies first/max results to the query', function () {
    [$qb] = makeQbWithQuery([new Translation('eng-GB', 'k', 'A')]);
    $qb->expects(testMock(PHPUnit\Framework\TestCase::class)->once())->method('setFirstResult')->with(10);
    $qb->expects(testMock(PHPUnit\Framework\TestCase::class)->once())->method('setMaxResults')->with(5);

    $repo = makeTranslationRepo($qb);
    expect($repo->findPagedByFilter('', '', '', 'id', 'ASC', 10, 5))->toHaveCount(1);
});

it('createExportQuery returns a query ordered by id', function () {
    [$qb] = makeQbWithQuery();
    $qb->expects(testMock(PHPUnit\Framework\TestCase::class)->once())->method('orderBy')->with('t.id', 'ASC');

    $repo = makeTranslationRepo($qb);
    expect($repo->createExportQuery())->toBeInstanceOf(Doctrine\ORM\Query::class);
});

it('findByTransKeysAndLanguages returns matching entities in one query', function () {
    $rows = [new Translation('eng-GB', 'a', 'A')];
    [$qb] = makeQbWithQuery($rows);
    $repo = makeTranslationRepo($qb);

    expect($repo->findByTransKeysAndLanguages(['a'], ['eng-GB']))->toBe($rows);
});

it('findByTransKeysAndLanguages returns an empty array without querying for empty input', function () {
    [$qb, $query] = makeQbWithQuery();
    $query->expects(testMock(PHPUnit\Framework\TestCase::class)->never())->method('getResult');
    $repo = makeTranslationRepo($qb);

    expect($repo->findByTransKeysAndLanguages([], ['eng-GB']))->toBe([])
        ->and($repo->findByTransKeysAndLanguages(['a'], []))->toBe([]);
});
