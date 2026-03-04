<?php

declare(strict_types=1);

use vardumper\IbexaThemeTranslationsBundle\Cache\StaticArrayTranslationCache;

uses(PHPUnit\Framework\TestCase::class);

function tempCacheDir(): string
{
    $dir = sys_get_temp_dir() . '/static_trans_test_' . uniqid('', true);
    return $dir;
}

afterEach(function () {
    // Clean up any temp dirs created during tests
    foreach (glob(sys_get_temp_dir() . '/static_trans_test_*') ?: [] as $dir) {
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
});

it('returns null when the cache file does not exist', function () {
    $cache = new StaticArrayTranslationCache(tempCacheDir());

    expect($cache->get('eng-GB', 'hello'))->toBeNull();
});

it('returns null when the key is not in the cache file', function () {
    $dir = tempCacheDir();
    $cache = new StaticArrayTranslationCache($dir);
    $cache->warmLanguage('eng-GB', ['hello' => 'Hello']);

    expect($cache->get('eng-GB', 'missing'))->toBeNull();
});

it('returns the cached value after warming', function () {
    $dir = tempCacheDir();
    $cache = new StaticArrayTranslationCache($dir);
    $cache->warmLanguage('eng-GB', ['hello' => 'Hello', 'bye' => 'Goodbye']);

    expect($cache->get('eng-GB', 'hello'))->toBe('Hello');
    expect($cache->get('eng-GB', 'bye'))->toBe('Goodbye');
});

it('creates the cache directory when warming if it does not exist', function () {
    $dir = tempCacheDir();
    $cache = new StaticArrayTranslationCache($dir);
    $cache->warmLanguage('eng-GB', ['key' => 'val']);

    expect(is_dir($dir))->toBeTrue();
});

it('writes a valid PHP file on warmLanguage', function () {
    $dir = tempCacheDir();
    $cache = new StaticArrayTranslationCache($dir);
    $cache->warmLanguage('eng-GB', ['greeting' => 'Hello']);

    $file = $dir . '/eng-GB.php';
    expect(is_file($file))->toBeTrue();

    $loaded = require $file;
    expect($loaded)->toBe(['greeting' => 'Hello']);
});

it('sanitizes special chars in language code for the filename', function () {
    $dir = tempCacheDir();
    $cache = new StaticArrayTranslationCache($dir);
    $cache->warmLanguage('eng-GB', ['k' => 'v']);

    expect(is_file($dir . '/eng-GB.php'))->toBeTrue();
});

it('updates the in-memory cache on warmLanguage', function () {
    $dir = tempCacheDir();
    $cache = new StaticArrayTranslationCache($dir);
    $cache->warmLanguage('eng-GB', ['a' => '1']);

    // Overwrite
    $cache->warmLanguage('eng-GB', ['a' => '2']);

    expect($cache->get('eng-GB', 'a'))->toBe('2');
});

it('invalidates a language cache file', function () {
    $dir = tempCacheDir();
    $cache = new StaticArrayTranslationCache($dir);
    $cache->warmLanguage('eng-GB', ['k' => 'v']);

    $cache->invalidateLanguage('eng-GB');

    // File should be deleted
    expect(is_file($dir . '/eng-GB.php'))->toBeFalse();
    // In-memory should be cleared too
    expect($cache->get('eng-GB', 'k'))->toBeNull();
});

it('invalidateLanguage is a no-op when file does not exist', function () {
    $cache = new StaticArrayTranslationCache(tempCacheDir());

    // Should not throw
    $cache->invalidateLanguage('eng-GB');

    expect(true)->toBeTrue();
});

it('invalidates all cache files', function () {
    $dir = tempCacheDir();
    $cache = new StaticArrayTranslationCache($dir);
    $cache->warmLanguage('eng-GB', ['k1' => 'v1']);
    $cache->warmLanguage('deu-DE', ['k2' => 'v2']);

    $cache->invalidateAll();

    expect($cache->get('eng-GB', 'k1'))->toBeNull();
    expect($cache->get('deu-DE', 'k2'))->toBeNull();
    expect(glob($dir . '/*.php'))->toBe([]);
});

it('invalidateAll is a no-op when the cache dir does not exist', function () {
    $cache = new StaticArrayTranslationCache('/nonexistent/path/xyz123');

    // Should not throw
    $cache->invalidateAll();

    expect(true)->toBeTrue();
});
