<?php

declare(strict_types=1);

namespace fork\IbexaThemeTranslationsBundle\Cache;

final class StaticArrayTranslationCache implements TranslationCacheInterface
{
    /**
     * @var array<string, array<string, string>> In-memory runtime cache
     */
    private array $loaded = [];

    public function __construct(
        private readonly string $cacheDir,
    ) {
    }

    public function get(string $languageCode, string $transKey): ?string
    {
        if (!isset($this->loaded[$languageCode])) {
            $file = $this->getFilePath($languageCode);
            if (!is_file($file)) {
                return null;
            }
            $this->loaded[$languageCode] = require $file;
        }

        return $this->loaded[$languageCode][$transKey] ?? null;
    }

    public function warmLanguage(string $languageCode, array $translations): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0775, true);
        }

        $file = $this->getFilePath($languageCode);
        $tmpFile = $file . '.' . uniqid('', true) . '.tmp';

        $content = "<?php\n\nreturn " . var_export($translations, true) . ";\n";

        file_put_contents($tmpFile, $content);
        rename($tmpFile, $file);

        if (function_exists('opcache_invalidate')) { /* force re-compilation on next include */
            opcache_invalidate($file, true);
        }

        $this->loaded[$languageCode] = $translations;
    }

    public function invalidateLanguage(string $languageCode): void
    {
        $file = $this->getFilePath($languageCode);
        if (is_file($file)) {
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($file, true);
            }
            @unlink($file);
        }
        unset($this->loaded[$languageCode]);
    }

    public function invalidateAll(): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        $files = glob($this->cacheDir . '/*.php');
        foreach ($files as $file) {
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($file, true);
            }
            @unlink($file);
        }
        $this->loaded = [];
    }

    private function getFilePath(string $languageCode): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $languageCode);

        return $this->cacheDir . '/' . $safe . '.php';
    }
}
