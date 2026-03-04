<?php

declare(strict_types=1);

use fork\IbexaThemeTranslationsBundle\Service\DeeplTranslationService;
use Ibexa\AutomatedTranslation\ClientProvider;
use Ibexa\Contracts\AutomatedTranslation\Client\ClientInterface;
use Ibexa\Core\MVC\Symfony\Locale\LocaleConverterInterface;

uses(PHPUnit\Framework\TestCase::class);

it('isConfigured returns false when no client provider is injected', function () {
    $localeConverter = $this->createMock(LocaleConverterInterface::class);
    $service = new DeeplTranslationService($localeConverter, null);

    expect($service->isConfigured())->toBeFalse();
});

it('isConfigured returns false when deepl is not in the client list', function () {
    $provider = $this->createMock(ClientProvider::class);
    $provider->method('getClients')->willReturn(['google' => $this->createMock(ClientInterface::class)]);

    $service = new DeeplTranslationService($this->createMock(LocaleConverterInterface::class), $provider);

    expect($service->isConfigured())->toBeFalse();
});

it('isConfigured returns true when deepl client is registered', function () {
    $provider = $this->createMock(ClientProvider::class);
    $provider->method('getClients')->willReturn(['deepl' => $this->createMock(ClientInterface::class)]);

    $service = new DeeplTranslationService($this->createMock(LocaleConverterInterface::class), $provider);

    expect($service->isConfigured())->toBeTrue();
});

it('translate throws RuntimeException when not configured', function () {
    $service = new DeeplTranslationService($this->createMock(LocaleConverterInterface::class), null);

    expect(fn () => $service->translate('Hello', null, 'deu-DE'))
        ->toThrow(\RuntimeException::class, 'DeepL is not configured');
});

it('translate wraps text, calls client, and unwraps XML response', function () {
    $client = $this->createMock(ClientInterface::class);
    $client->expects($this->once())
        ->method('translate')
        ->willReturnCallback(function (string $wrapped, ?string $from, string $to): string {
            // Echo the wrapped text back with translated content
            return '<deepl>Hallo</deepl>';
        });

    $provider = $this->createMock(ClientProvider::class);
    $provider->method('getClients')->willReturn(['deepl' => $client]);
    $provider->method('get')->with('deepl')->willReturn($client);

    $localeConverter = $this->createMock(LocaleConverterInterface::class);
    $localeConverter->method('convertToPOSIX')->willReturn('de_DE');

    $service = new DeeplTranslationService($localeConverter, $provider);
    $result = $service->translate('Hello', 'eng-GB', 'ger-DE');

    expect($result)->toBe('Hallo');
});

it('translate handles null source language', function () {
    $client = $this->createMock(ClientInterface::class);
    $client->method('translate')->willReturn('<deepl>Bonjour</deepl>');

    $provider = $this->createMock(ClientProvider::class);
    $provider->method('getClients')->willReturn(['deepl' => $client]);
    $provider->method('get')->willReturn($client);

    $localeConverter = $this->createMock(LocaleConverterInterface::class);
    $localeConverter->method('convertToPOSIX')->willReturn('fr_FR');

    $service = new DeeplTranslationService($localeConverter, $provider);
    $result = $service->translate('Hello', null, 'fra-FR');

    expect($result)->toBe('Bonjour');
});

it('translate falls back to raw result when XML wrapper is missing', function () {
    $client = $this->createMock(ClientInterface::class);
    $client->method('translate')->willReturn('Hallo');

    $provider = $this->createMock(ClientProvider::class);
    $provider->method('getClients')->willReturn(['deepl' => $client]);
    $provider->method('get')->willReturn($client);

    $localeConverter = $this->createMock(LocaleConverterInterface::class);
    $localeConverter->method('convertToPOSIX')->willReturn('de_DE');

    $service = new DeeplTranslationService($localeConverter, $provider);
    $result = $service->translate('Hello', null, 'ger-DE');

    expect($result)->toBe('Hallo');
});
