<?php

declare(strict_types=1);

use Ibexa\Contracts\AutomatedTranslation\Exception\ClientNotConfiguredException;
use vardumper\IbexaThemeTranslationsBundle\Client\Deepl;

uses(PHPUnit\Framework\TestCase::class);

class FakeDeeplResponseBody
{
    public function __construct(private string $contents)
    {
    }

    public function getContents(): string
    {
        return $this->contents;
    }
}

class FakeDeeplResponse
{
    public function __construct(private string $text)
    {
    }

    public function getBody(): FakeDeeplResponseBody
    {
        return new FakeDeeplResponseBody(json_encode([
            'translations' => [
                ['text' => $this->text],
            ],
        ]));
    }
}

class FakeDeeplHttpClient
{
    public static array $lastConfig = [];
    public static array $lastPost = [];
    public static string $nextText = 'FAKE_TRANSLATION';

    public function __construct(array $config = [])
    {
        self::$lastConfig = $config;
    }

    public function post(string $path, array $options = []): FakeDeeplResponse
    {
        self::$lastPost = [
            'path' => $path,
            'options' => $options,
        ];

        return new FakeDeeplResponse(self::$nextText);
    }
}

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

it('supportsLanguage uses the 2-letter fallback key in custom language map', function () {
    $client = new Deepl(['EN' => 'EN-US']);

    expect($client->supportsLanguage('en-AU'))->toBeTrue();
});

it('translate uses the free API endpoint when auth key ends with :fx', function () {
    FakeDeeplHttpClient::$nextText = 'Hallo';
    FakeDeeplHttpClient::$lastConfig = [];
    FakeDeeplHttpClient::$lastPost = [];

    $client = new Deepl([], static fn (array $config) => new FakeDeeplHttpClient($config));
    $client->setConfiguration(['authKey' => 'test-key:fx']);
    $translated = $client->translate('<deepl>Hello</deepl>', 'EN-GB', 'DE');

    expect($translated)->toBe('Hallo');
    expect(FakeDeeplHttpClient::$lastConfig['base_uri'])->toBe('https://api-free.deepl.com');
    expect(FakeDeeplHttpClient::$lastConfig['headers']['Authorization'])->toBe('DeepL-Auth-Key test-key:fx');
    expect(FakeDeeplHttpClient::$lastPost['path'])->toBe('/v2/translate');
    expect(FakeDeeplHttpClient::$lastPost['options']['form_params']['target_lang'])->toBe('DE');
    expect(FakeDeeplHttpClient::$lastPost['options']['form_params']['source_lang'])->toBe('EN');
});

it('translate uses the pro API endpoint when auth key has no :fx suffix', function () {
    FakeDeeplHttpClient::$nextText = 'Hello';
    FakeDeeplHttpClient::$lastConfig = [];
    FakeDeeplHttpClient::$lastPost = [];

    $client = new Deepl([], static fn (array $config) => new FakeDeeplHttpClient($config));
    $client->setConfiguration(['authKey' => 'pro-key']);
    $translated = $client->translate('<deepl>Hallo</deepl>', null, 'EN-GB');

    expect($translated)->toBe('Hello');
    expect(FakeDeeplHttpClient::$lastConfig['base_uri'])->toBe('https://api.deepl.com');
    expect(FakeDeeplHttpClient::$lastPost['options']['form_params']['target_lang'])->toBe('EN-GB');
    expect(array_key_exists('source_lang', FakeDeeplHttpClient::$lastPost['options']['form_params']))->toBeFalse();
});

it('translate throws when target language code is invalid', function () {
    $client = new Deepl([], static fn (array $config) => new FakeDeeplHttpClient($config));
    $client->setConfiguration(['authKey' => 'test-key:fx']);

    expect(fn () => $client->translate('Hello', 'EN', 'INVALID_XX'))
        ->toThrow(Ibexa\AutomatedTranslation\Exception\InvalidLanguageCodeException::class);
});
