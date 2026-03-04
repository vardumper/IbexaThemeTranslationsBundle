<?php

declare(strict_types=1);

use fork\IbexaThemeTranslationsBundle\Cache\PublicTranslationsCache;

uses(PHPUnit\Framework\TestCase::class);

function publicTempDir(): string
{
    return sys_get_temp_dir() . '/public_trans_test_' . uniqid('', true);
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir() . '/public_trans_test_*') ?: [] as $dir) {
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
});

it('get() always returns null (write-only cache tier)', function () {
    $cache = new PublicTranslationsCache(publicTempDir());

    expect($cache->get('eng-GB', 'any'))->toBeNull();
});

it('warmLanguage creates the output directory if it does not exist', function () {
    $dir = publicTempDir();
    $cache = new PublicTranslationsCache($dir);
    $cache->warmLanguage('eng-GB', ['k' => 'v']);

    expect(is_dir($dir))->toBeTrue();
});

it('warmLanguage writes a JSON file', function () {
    $dir = publicTempDir();
    $cache = new PublicTranslationsCache($dir);
    $cache->warmLanguage('eng-GB', ['hello' => 'Hello']);

    $jsonFile = $dir . '/eng-GB.json';
    expect(is_file($jsonFile))->toBeTrue();

    $data = json_decode(file_get_contents($jsonFile), true);
    expect($data)->toBe(['hello' => 'Hello']);
});

it('warmLanguage writes a TypeScript file', function () {
    $dir = publicTempDir();
    $cache = new PublicTranslationsCache($dir);
    $cache->warmLanguage('eng-GB', ['key' => 'Value']);

    $tsFile = $dir . '/eng-GB.ts';
    expect(is_file($tsFile))->toBeTrue();
    expect(file_get_contents($tsFile))->toContain('export default translations');
    expect(file_get_contents($tsFile))->toContain('"key": "Value"');
});

it('warmLanguage sanitizes the language code for filenames', function () {
    $dir = publicTempDir();
    $cache = new PublicTranslationsCache($dir);
    $cache->warmLanguage('eng-GB', []);

    // Hyphen is safe; should produce eng-GB.json
    expect(is_file($dir . '/eng-GB.json'))->toBeTrue();
});

it('invalidateLanguage removes the JSON and TS files', function () {
    $dir = publicTempDir();
    $cache = new PublicTranslationsCache($dir);
    $cache->warmLanguage('eng-GB', ['k' => 'v']);
    $cache->invalidateLanguage('eng-GB');

    expect(is_file($dir . '/eng-GB.json'))->toBeFalse();
    expect(is_file($dir . '/eng-GB.ts'))->toBeFalse();
});

it('invalidateLanguage is a no-op when files do not exist', function () {
    $cache = new PublicTranslationsCache(publicTempDir());
    // Should not throw
    $cache->invalidateLanguage('eng-GB');

    expect(true)->toBeTrue();
});

it('invalidateAll removes all JSON and TS files', function () {
    $dir = publicTempDir();
    $cache = new PublicTranslationsCache($dir);
    $cache->warmLanguage('eng-GB', ['a' => '1']);
    $cache->warmLanguage('deu-DE', ['b' => '2']);
    $cache->invalidateAll();

    expect(glob($dir . '/*.json'))->toBe([]);
    expect(glob($dir . '/*.ts'))->toBe([]);
});

it('invalidateAll is a no-op when the output dir does not exist', function () {
    $cache = new PublicTranslationsCache('/nonexistent/xyz_test_dir_123');
    // Should not throw
    $cache->invalidateAll();

    expect(true)->toBeTrue();
});
