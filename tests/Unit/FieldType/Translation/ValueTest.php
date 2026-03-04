<?php

declare(strict_types=1);

use fork\IbexaThemeTranslationsBundle\FieldType\Translation\Value;

uses(PHPUnit\Framework\TestCase::class);

it('returns an empty string when cast to string with no translation', function () {
    $v = new Value();

    expect((string) $v)->toBe('');
});

it('returns the translation when cast to string', function () {
    $v = new Value();
    $v->setTranslation('Hello');

    expect((string) $v)->toBe('Hello');
});

it('sets and gets the trans key', function () {
    $v = new Value();
    $v->setTransKey('my.key');

    expect($v->getTransKey())->toBe('my.key');
});

it('sets and gets the language code', function () {
    $v = new Value();
    $v->setLanguageCode('eng-GB');

    expect($v->getLanguageCode())->toBe('eng-GB');
});

it('sets and gets the translation', function () {
    $v = new Value();
    $v->setTranslation('World');

    expect($v->getTranslation())->toBe('World');
});

it('sets and gets the id', function () {
    $v = new Value();
    $v->setId(99);

    expect($v->getId())->toBe(99);
});

it('allows setting translation to null', function () {
    $v = new Value();
    $v->setTranslation('x');
    $v->setTranslation(null);

    expect($v->getTranslation())->toBeNull();
    expect((string) $v)->toBe('');
});
