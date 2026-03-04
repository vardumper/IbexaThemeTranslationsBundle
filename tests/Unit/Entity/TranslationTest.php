<?php

declare(strict_types=1);

use fork\IbexaThemeTranslationsBundle\Entity\Translation;
use fork\IbexaThemeTranslationsBundle\FieldType\Translation\Value;

uses(PHPUnit\Framework\TestCase::class);

it('constructs with language code, trans key, and optional translation', function () {
    $t = new Translation('eng-GB', 'hello', 'Hello');

    expect($t->getLanguageCode())->toBe('eng-GB')
        ->and($t->getTransKey())->toBe('hello')
        ->and($t->getTranslation())->toBe('Hello');
});

it('constructs with null translation by default', function () {
    $t = new Translation('eng-GB', 'hello');

    expect($t->getTranslation())->toBeNull();
});

it('creates via static factory', function () {
    $t = Translation::create('deu-DE', 'bye', 'Tschüss');

    expect($t->getLanguageCode())->toBe('deu-DE')
        ->and($t->getTransKey())->toBe('bye')
        ->and($t->getTranslation())->toBe('Tschüss');
});

it('creates from array with id', function () {
    $t = Translation::fromArray([
        'id' => 42,
        'languageCode' => 'eng-GB',
        'transKey' => 'greeting',
        'translation' => 'Hello',
    ]);

    expect($t->getId())->toBe(42)
        ->and($t->getLanguageCode())->toBe('eng-GB')
        ->and($t->getTransKey())->toBe('greeting')
        ->and($t->getTranslation())->toBe('Hello');
});

it('creates from array without id', function () {
    $t = Translation::fromArray([
        'languageCode' => 'eng-GB',
        'transKey' => 'key',
        'translation' => null,
    ]);

    expect($t->getId())->toBeNull();
});

it('creates from form value object', function () {
    $value = new Value();
    $value->setLanguageCode('fra-FR');
    $value->setTransKey('bonjour');
    $value->setTranslation('Hello');

    $t = Translation::fromFormData($value);

    expect($t->getLanguageCode())->toBe('fra-FR')
        ->and($t->getTransKey())->toBe('bonjour')
        ->and($t->getTranslation())->toBe('Hello');
});

it('allows setting language code via setter', function () {
    $t = new Translation('eng-GB', 'key');
    $result = $t->setLanguageCode('deu-DE');

    expect($result)->toBe($t)
        ->and($t->getLanguageCode())->toBe('deu-DE');
});

it('allows setting trans key via setter', function () {
    $t = new Translation('eng-GB', 'old');
    $result = $t->setTransKey('new');

    expect($result)->toBe($t)
        ->and($t->getTransKey())->toBe('new');
});

it('allows setting translation via setter', function () {
    $t = new Translation('eng-GB', 'key');
    $result = $t->setTranslation('value');

    expect($result)->toBe($t)
        ->and($t->getTranslation())->toBe('value');
});

it('allows setting id via setter', function () {
    $t = new Translation('eng-GB', 'key');
    $result = $t->setId(7);

    expect($result)->toBe($t)
        ->and($t->getId())->toBe(7);
});

it('serializes to JSON', function () {
    $t = new Translation('eng-GB', 'greet', 'Hi');
    $t->setId(1);

    expect($t->jsonSerialize())->toBe([
        'id' => 1,
        'transKey' => 'greet',
        'languageCode' => 'eng-GB',
        'translation' => 'Hi',
    ]);
});
