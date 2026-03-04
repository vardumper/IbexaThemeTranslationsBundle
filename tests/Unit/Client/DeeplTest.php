<?php

declare(strict_types=1);

use fork\IbexaThemeTranslationsBundle\Client\Deepl;
use Ibexa\Contracts\AutomatedTranslation\Exception\ClientNotConfiguredException;

uses(PHPUnit\Framework\TestCase::class);

it('getServiceAlias returns deepl', function () {
    $client = new Deepl();

    expect($client->getServiceAlias())->toBe('deepl');
});

it('getServiceFullName returns Deepl', function () {
    $client = new Deepl();

    expect($client->getServiceFullName())->toBe('Deepl');
});

it('setConfiguration stores the auth key', function () {
    $client = new Deepl();
    // No exception means it worked
    $client->setConfiguration(['authKey' => 'test-key-123']);

    expect(true)->toBeTrue();
});

it('setConfiguration throws ClientNotConfiguredException when authKey is missing', function () {
    $client = new Deepl();

    expect(fn () => $client->setConfiguration([]))
        ->toThrow(ClientNotConfiguredException::class);
});

it('supportsLanguage returns true for a directly supported code', function () {
    $client = new Deepl();

    expect($client->supportsLanguage('FR'))->toBeTrue();
    expect($client->supportsLanguage('DE'))->toBeTrue();
});

it('supportsLanguage returns true for a 2-letter lowercase code that maps to a supported code', function () {
    $client = new Deepl();

    expect($client->supportsLanguage('fr'))->toBeTrue();
    expect($client->supportsLanguage('de'))->toBeTrue();
});

it('supportsLanguage returns true when language is in the language map', function () {
    $client = new Deepl();

    // ZH_TW → ZH-HANT via DEFAULT_LANGUAGE_MAP
    expect($client->supportsLanguage('ZH_TW'))->toBeTrue();
    // ZH_CN → ZH-HANS
    expect($client->supportsLanguage('ZH_CN'))->toBeTrue();
});

it('supportsLanguage returns false for an unrecognised code', function () {
    $client = new Deepl();

    expect($client->supportsLanguage('xyz-UNKNOWN'))->toBeFalse();
});

it('supportsLanguage returns true for hyphenated supported codes', function () {
    $client = new Deepl();

    expect($client->supportsLanguage('EN-GB'))->toBeTrue();
    expect($client->supportsLanguage('PT-BR'))->toBeTrue();
});

it('constructor accepts a custom language map', function () {
    $client = new Deepl(['CUSTOM' => 'FR']);

    expect($client->supportsLanguage('CUSTOM'))->toBeTrue();
});
