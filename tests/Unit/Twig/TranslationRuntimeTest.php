<?php

declare(strict_types=1);

use vardumper\IbexaThemeTranslationsBundle\Service\LanguageResolverInterface;
use vardumper\IbexaThemeTranslationsBundle\Service\TranslationServiceInterface;
use vardumper\IbexaThemeTranslationsBundle\Twig\TranslationRuntime;

uses(PHPUnit\Framework\TestCase::class);

function makeRuntime(
    TranslationServiceInterface $translationService,
    LanguageResolverInterface $languageResolver,
    string $defaultLanguage = 'eng-GB',
): TranslationRuntime {
    return new TranslationRuntime($translationService, $languageResolver, $defaultLanguage);
}

it('returns the translation for the current language', function () {
    $service = $this->createMock(TranslationServiceInterface::class);
    $service->expects($this->once())
        ->method('translate')
        ->with('hello', 'deu-DE')
        ->willReturn('Hallo');

    $resolver = $this->createMock(LanguageResolverInterface::class);
    $resolver->method('getCurrentLanguage')->willReturn('deu-DE');

    $runtime = makeRuntime($service, $resolver);

    expect($runtime->l10n('hello'))->toBe('Hallo');
});

it('uses the override language code when provided', function () {
    $service = $this->createMock(TranslationServiceInterface::class);
    $service->expects($this->once())
        ->method('translate')
        ->with('hello', 'fra-FR')
        ->willReturn('Bonjour');

    $resolver = $this->createMock(LanguageResolverInterface::class);
    $resolver->expects($this->never())->method('getCurrentLanguage');

    $runtime = makeRuntime($service, $resolver);

    expect($runtime->l10n('hello', 'fra-FR'))->toBe('Bonjour');
});

it('falls back to the default language when translation is missing', function () {
    $service = $this->createMock(TranslationServiceInterface::class);
    $service->method('translate')
        ->willReturnMap([
            ['hello', 'deu-DE', ''],
            ['hello', 'eng-GB', 'Hello'],
        ]);

    $resolver = $this->createMock(LanguageResolverInterface::class);
    $resolver->method('getCurrentLanguage')->willReturn('deu-DE');

    $runtime = makeRuntime($service, $resolver, 'eng-GB');

    expect($runtime->l10n('hello'))->toBe('Hello');
});

it('does not fall back when the current language is already the default language', function () {
    $service = $this->createMock(TranslationServiceInterface::class);
    $service->expects($this->once())
        ->method('translate')
        ->with('hello', 'eng-GB')
        ->willReturn('');

    $resolver = $this->createMock(LanguageResolverInterface::class);
    $resolver->method('getCurrentLanguage')->willReturn('eng-GB');

    $runtime = makeRuntime($service, $resolver, 'eng-GB');

    expect($runtime->l10n('hello'))->toBe('');
});

it('returns the current-language translation without fallback when it is non-empty', function () {
    $service = $this->createMock(TranslationServiceInterface::class);
    $service->expects($this->once())
        ->method('translate')
        ->with('hello', 'deu-DE')
        ->willReturn('Hallo');

    $resolver = $this->createMock(LanguageResolverInterface::class);
    $resolver->method('getCurrentLanguage')->willReturn('deu-DE');

    $runtime = makeRuntime($service, $resolver);

    expect($runtime->l10n('hello'))->toBe('Hallo');
});
