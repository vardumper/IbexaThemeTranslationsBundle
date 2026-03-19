<?php

declare(strict_types=1);

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use vardumper\IbexaThemeTranslationsBundle\Cache\TranslationCacheInterface;
use vardumper\IbexaThemeTranslationsBundle\Cache\TranslationCacheWarmer;
use vardumper\IbexaThemeTranslationsBundle\Command\WarmupTranslationCacheCommand;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationRepository;

uses(PHPUnit\Framework\TestCase::class);

/**
 * TranslationCacheWarmer is final, so we use a real instance with mocked deps.
 * We verify behaviour by asserting interactions on the injected repo and cache.
 */
function makeCommandAndCacheMock(array $languages = ['eng-GB']): array
{
    $repo = testMock(TranslationRepository::class);
    $repo->method('findAllLanguageCodes')->willReturn($languages);
    $repo->method('findAllByLanguageCodeAsKeyValueMap')->willReturn([]);

    $cache = testMock(TranslationCacheInterface::class);

    $warmer = new TranslationCacheWarmer($repo, [$cache]);
    $command = new WarmupTranslationCacheCommand($warmer);
    $tester = new CommandTester($command);

    return [$tester, $repo, $cache];
}

it('warms all languages when no options are passed', function () {
    [$tester, $repo, $cache] = makeCommandAndCacheMock(['eng-GB', 'deu-DE']);

    $cache->expects($this->exactly(2))->method('warmLanguage');
    $cache->expects($this->never())->method('invalidateAll');

    $exitCode = $tester->execute([]);

    expect($exitCode)->toBe(Command::SUCCESS);
    expect($tester->getDisplay())->toContain('Warming translation cache for all languages');
    expect($tester->getDisplay())->toContain('Translation cache warmup complete');
});

it('clears all caches then warms all when --clear is passed', function () {
    [$tester, $repo, $cache] = makeCommandAndCacheMock(['eng-GB']);

    $cache->expects($this->once())->method('invalidateAll');
    $cache->expects($this->once())->method('warmLanguage');

    $exitCode = $tester->execute(['--clear' => true]);

    expect($exitCode)->toBe(Command::SUCCESS);
    expect($tester->getDisplay())->toContain('Clearing all translation caches');
});

it('warms a specific language when --language is passed', function () {
    [$tester, $repo, $cache] = makeCommandAndCacheMock();

    $repo->expects($this->once())->method('findAllByLanguageCodeAsKeyValueMap')->with('eng-GB');
    $cache->expects($this->never())->method('invalidateAll');
    $cache->expects($this->once())->method('warmLanguage');

    $exitCode = $tester->execute(['--language' => 'eng-GB']);

    expect($exitCode)->toBe(Command::SUCCESS);
    expect($tester->getDisplay())->toContain('Warming translation cache for language: eng-GB');
});

it('clears then warms a specific language when both --clear and --language are passed', function () {
    [$tester, $repo, $cache] = makeCommandAndCacheMock();

    $cache->expects($this->once())->method('invalidateAll');
    $cache->expects($this->once())->method('warmLanguage');

    $exitCode = $tester->execute(['--clear' => true, '--language' => 'deu-DE']);

    expect($exitCode)->toBe(Command::SUCCESS);
    expect($tester->getDisplay())->toContain('Translation cache warmup complete');
});
