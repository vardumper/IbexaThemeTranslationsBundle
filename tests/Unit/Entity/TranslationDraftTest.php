<?php

declare(strict_types=1);

use fork\IbexaThemeTranslationsBundle\Entity\TranslationDraft;

uses(PHPUnit\Framework\TestCase::class);

it('constructs with language code, trans key, and optional translation', function () {
    $d = new TranslationDraft('eng-GB', 'hello', 'Hello');

    expect($d->getLanguageCode())->toBe('eng-GB')
        ->and($d->getTransKey())->toBe('hello')
        ->and($d->getTranslation())->toBe('Hello');
});

it('constructs with null translation by default', function () {
    $d = new TranslationDraft('eng-GB', 'hello');

    expect($d->getTranslation())->toBeNull();
});

it('has a null id before persistence', function () {
    $d = new TranslationDraft('eng-GB', 'key');

    expect($d->getId())->toBeNull();
});

it('has a DateTimeImmutable createdAt timestamp', function () {
    $before = new \DateTimeImmutable();
    $d = new TranslationDraft('eng-GB', 'key');
    $after = new \DateTimeImmutable();

    expect($d->getCreatedAt())->toBeInstanceOf(\DateTimeImmutable::class);
    expect($d->getCreatedAt()->getTimestamp())->toBeGreaterThanOrEqual($before->getTimestamp());
    expect($d->getCreatedAt()->getTimestamp())->toBeLessThanOrEqual($after->getTimestamp());
});

it('allows updating translation via setter', function () {
    $d = new TranslationDraft('eng-GB', 'key', 'old');
    $result = $d->setTranslation('new');

    expect($result)->toBe($d)
        ->and($d->getTranslation())->toBe('new');
});

it('allows setting translation to null', function () {
    $d = new TranslationDraft('eng-GB', 'key', 'value');
    $d->setTranslation(null);

    expect($d->getTranslation())->toBeNull();
});
