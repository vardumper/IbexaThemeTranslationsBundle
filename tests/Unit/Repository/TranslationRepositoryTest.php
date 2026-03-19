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

function makeQbWithQuery(array $result = [], array $scalarResult = [], mixed $oneOrNull = null, int $execute = 1): array
{
    $query = testMock(Doctrine\ORM\AbstractQuery::class);
    $query->method('getResult')->willReturn($result);
    $query->method('getScalarResult')->willReturn($scalarResult);
    $query->method('getOneOrNullResult')->willReturn($oneOrNull);
    $query->method('execute')->willReturn($execute);

    $expr = testMock(Expr::class);
    $expr->method('exists')->willReturn('EXISTS_SUBQUERY');

    $qb = testMock(QueryBuilder::class);
    $qb->method('select')->willReturnSelf();
    $qb->method('andWhere')->willReturnSelf();
    $qb->method('where')->willReturnSelf();
    $qb->method('setParameter')->willReturnSelf();
    $qb->method('setMaxResults')->willReturnSelf();
    $qb->method('orderBy')->willReturnSelf();
    $qb->method('delete')->willReturnSelf();
    $qb->method('getQuery')->willReturn($query);
    $qb->method('expr')->willReturn($expr);

    return [$qb, $query, $expr];
}

it('translate returns translation from query result', function () {
    [$qb] = makeQbWithQuery([['translation' => 'Hello']]);
    $repo = makeTranslationRepo($qb);

    expect($repo->translate('hello.key', 'eng-GB'))->toBe('Hello');
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

it('findByStatus missing uses the expected where clause', function () {
    [$qb] = makeQbWithQuery([]);
    $qb->expects(testMock(PHPUnit\Framework\TestCase::class)->once())
        ->method('andWhere')
        ->with("t.translation = '' OR t.translation IS NULL")
        ->willReturnSelf();

    $repo = makeTranslationRepo($qb);
    $repo->findByStatus('missing');

    expect(true)->toBeTrue();
});

it('findByStatus done uses the expected where clause', function () {
    [$qb] = makeQbWithQuery([]);
    $qb->expects(testMock(PHPUnit\Framework\TestCase::class)->once())
        ->method('andWhere')
        ->with("t.translation != '' OR t.translation IS NOT NULL")
        ->willReturnSelf();

    $repo = makeTranslationRepo($qb);
    $repo->findByStatus('done');

    expect(true)->toBeTrue();
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
    [$qb] = makeQbWithQuery([
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

it('truncate executes delete and auto-increment reset query', function () {
    [$qb, $query] = makeQbWithQuery([], [], null, 1);
    $query->expects(testMock(PHPUnit\Framework\TestCase::class)->once())->method('execute')->willReturn(1);

    $conn = testMock(Doctrine\DBAL\Connection::class);
    $conn->expects(testMock(PHPUnit\Framework\TestCase::class)->once())
        ->method('executeQuery')
        ->with('ALTER TABLE `translation` AUTO_INCREMENT = 1;');

    $em = testMock(EntityManagerInterface::class);
    $em->method('getConnection')->willReturn($conn);

    $repo = makeTranslationRepo($qb);

    $ref = new ReflectionObject($repo);
    $prop = $ref->getProperty('_em');
    $prop->setAccessible(true);
    $prop->setValue($repo, $em);

    $repo->truncate();

    expect(true)->toBeTrue();
});
