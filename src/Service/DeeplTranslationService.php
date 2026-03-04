<?php

declare(strict_types=1);

namespace fork\IbexaThemeTranslationsBundle\Service;

use Ibexa\AutomatedTranslation\ClientProvider;
use Ibexa\Core\MVC\Symfony\Locale\LocaleConverterInterface;

final class DeeplTranslationService
{
    public function __construct(
        private readonly LocaleConverterInterface $localeConverter,
        private readonly ?ClientProvider $clientProvider = null,
    ) {
    }

    public function isConfigured(): bool
    {
        if ($this->clientProvider === null) {
            return false;
        }

        return array_key_exists('deepl', $this->clientProvider->getClients());
    }

    /**
     * Translate a plain-text string via DeepL.
     *
     * Ibexa locales (e.g. 'eng-GB', 'ger-DE') are converted to POSIX format
     * (e.g. 'en_GB', 'de_DE') before being passed to the DeepL client, whose
     * normalized() method correctly maps 2-letter POSIX prefixes to DeepL codes.
     *
     * @param string      $text  Source text
     * @param string|null $from  Ibexa locale (e.g. 'eng-GB'), or null for auto-detect
     * @param string      $to    Ibexa locale (e.g. 'ger-DE')
     */
    public function translate(string $text, ?string $from, string $to): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('DeepL is not configured.');
        }

        $client = $this->clientProvider->get('deepl');

        $posixTo = $this->localeConverter->convertToPOSIX($to) ?? $to; /* Ibexa 3-letter locale → POSIX so DeepL can resolve via 2-letter ISO 639-1 prefix */
        $posixFrom = $from !== null ? ($this->localeConverter->convertToPOSIX($from) ?? $from) : null;

        $wrapped = '<deepl>' . htmlspecialchars($text, ENT_XML1 | ENT_QUOTES) . '</deepl>'; /* XML wrapper prevents DeepL from mangling < > & with tag_handling=xml */
        $result = $client->translate($wrapped, $posixFrom, $posixTo);

        if (preg_match('/<deepl>(.*?)<\/deepl>/s', $result, $matches)) {
            return html_entity_decode($matches[1], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }

        return $result; /* fallback: should not happen if DeepL returns valid XML */
    }
}
