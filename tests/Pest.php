<?php

set_error_handler(function ($errno, $errstr) {
    if (str_contains($errstr, 'Implicitly marking parameter $beta as nullable')) {
        return true; // Stop the error from propagating
    }
    return false; // Let other errors pass through
}, E_DEPRECATED | E_USER_DEPRECATED);
/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(PHPUnit\Framework\TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toBeTwo', function () {
    return $this->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the amount of code duplication.
|
*/

function something()
{
    // ..
}

/**
 * Create a PHPUnit mock from global scope.
 * createMock() is final+protected in TestCase, so we expose it via a public wrapper.
 */
function testMock(string $class): PHPUnit\Framework\MockObject\MockObject
{
    static $factory;
    if ($factory === null) {
        $factory = new class('test') extends PHPUnit\Framework\TestCase {
            public function mock(string $class): PHPUnit\Framework\MockObject\MockObject
            {
                return $this->createMock($class);
            }
        };
    }
    return $factory->mock($class);
}
/**
 * Bootstrap a fresh in-memory SQLite EntityManager (with schema) for each call.
 * Fast enough for unit tests since SQLite in-memory creation is ~<5 ms.
 */
function sqliteEm(): Doctrine\ORM\EntityManagerInterface
{
    $config = Doctrine\ORM\ORMSetup::createAttributeMetadataConfiguration(
        [__DIR__ . '/../src/Entity/'],
        true,
        sys_get_temp_dir() . '/doctrine_proxies',
    );
    $config->setNamingStrategy(new Doctrine\ORM\Mapping\UnderscoreNamingStrategy());

    $connection = Doctrine\DBAL\DriverManager::getConnection([
        'driver' => 'pdo_sqlite',
        'memory' => true,
    ]);

    $em = new Doctrine\ORM\EntityManager($connection, $config);

    $schemaTool = new Doctrine\ORM\Tools\SchemaTool($em);
    $schemaTool->createSchema([
        $em->getClassMetadata(vardumper\IbexaThemeTranslationsBundle\Entity\Translation::class),
        $em->getClassMetadata(vardumper\IbexaThemeTranslationsBundle\Entity\TranslationDraft::class),
    ]);

    return $em;
}

/**
 * Create a concrete repository backed by a real SQLite EntityManager.
 * $repoClass must extend ServiceEntityRepository.
 */
function sqliteRepo(string $repoClass, Doctrine\ORM\EntityManagerInterface $em): object
{
    $registry = testMock(Doctrine\Persistence\ManagerRegistry::class);
    $registry->method('getManagerForClass')->willReturn($em);

    return new $repoClass($registry);
}