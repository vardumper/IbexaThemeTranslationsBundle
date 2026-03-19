<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Client;

use GuzzleHttp\Client;
use Ibexa\AutomatedTranslation\Exception\InvalidLanguageCodeException;
use Ibexa\Contracts\AutomatedTranslation\Client\ClientInterface;
use Ibexa\Contracts\AutomatedTranslation\Exception\ClientNotConfiguredException;

/**
 * Drop-in replacement for Ibexa\AutomatedTranslation\Client\Deepl that
 * auto-selects the free or pro API endpoint based on the auth key suffix.
 *
 * Free keys end with ':fx'  → https://api-free.deepl.com
 * Pro  keys have no suffix  → https://api.deepl.com
 *
 * The class short name is intentionally kept as "Deepl" so that
 * ClientProvider resolves it to the config key 'deepl' (via reflection).
 * Since our bundle is loaded after IbexaAutomatedTranslationBundle, this
 * service overwrites the original in the ClientProvider registry.
 */
final class Deepl implements ClientInterface
{
    private const API_PRO = 'https://api.deepl.com';
    private const API_FREE = 'https://api-free.deepl.com';

    /**
     * @see https://developers.deepl.com/docs/resources/supported-languages
     */
    private const LANGUAGE_CODES = [
        'AR', 'BG', 'CS', 'DA', 'DE', 'EL', 'EN-GB', 'EN-US', 'ES', 'ET', 'FI', 'FR',
        'HU', 'ID', 'IT', 'JA', 'KO', 'LT', 'LV', 'NB', 'NL', 'PL', 'PT-BR', 'PT-PT', 'RO',
        'RU', 'SK', 'SL', 'SV', 'TR', 'UK', 'ZH-HANS', 'ZH-HANT',
    ];

    /**
     * Default language map — mirrors ibexa/automated-translation default_settings.yaml
     */
    private const DEFAULT_LANGUAGE_MAP = [
        'ZH_TW' => 'ZH-HANT',
        'ZH_CN' => 'ZH-HANS',
        'ZH_HK' => 'ZH-HANS',
        'ZH' => 'ZH-HANS',
        'EN_GB' => 'EN-GB',
        'EN' => 'EN-US',
        'PT-BR' => 'PT-BR',
        'PT' => 'PT-PT',
    ];

    private string $authKey = '';

    /**
     * @var array<string, string>
     */
    private array $languageMap;

    /**
     * @var null|callable(array): object
     */
    private $httpClientFactory;

    public function __construct(array $languageMap = [], ?callable $httpClientFactory = null)
    {
        $this->languageMap = $languageMap ?: self::DEFAULT_LANGUAGE_MAP;
        $this->httpClientFactory = $httpClientFactory;
    }

    public function getServiceAlias(): string
    {
        return 'deepl';
    }

    public function getServiceFullName(): string
    {
        return 'Deepl';
    }

    public function setConfiguration(array $configuration): void
    {
        if (!isset($configuration['authKey'])) {
            throw new ClientNotConfiguredException('authKey is required');
        }
        $this->authKey = $configuration['authKey'];
    }

    public function translate(string $payload, ?string $from, string $to): string
    {
        $baseUri = str_ends_with($this->authKey, ':fx') ? self::API_FREE : self::API_PRO; /* free keys end with ':fx', pro keys have no suffix */

        $parameters = [
            'target_lang' => $this->normalized($to),
            'tag_handling' => 'xml',
            'text' => $payload,
        ];

        if ($from !== null) {
            $parameters['source_lang'] = substr($this->normalized($from), 0, 2);
        }

        $clientConfig = [
            'base_uri' => $baseUri,
            'timeout' => 5.0,
            'headers' => [
                'Authorization' => 'DeepL-Auth-Key ' . $this->authKey,
            ],
        ];

        $http = $this->httpClientFactory !== null
            ? ($this->httpClientFactory)($clientConfig)
            : new Client($clientConfig);

        $response = $http->post('/v2/translate', [
            'form_params' => $parameters,
        ]);
        $json = json_decode($response->getBody()->getContents());

        return $json->translations[0]->text;
    }

    public function supportsLanguage(string $languageCode): bool
    {
        try {
            return \in_array($this->normalized($languageCode), self::LANGUAGE_CODES);
        } catch (InvalidLanguageCodeException) {
            return false;
        }
    }

    private function normalized(string $languageCode): string
    {
        if (\in_array($languageCode, self::LANGUAGE_CODES)) {
            return $languageCode;
        }

        $code = strtoupper(substr($languageCode, 0, 2));
        if (\in_array($code, self::LANGUAGE_CODES)) {
            return $code;
        }

        $languageCode = strtoupper($languageCode);

        if (isset($this->languageMap[$languageCode])) {
            return $this->languageMap[$languageCode];
        }

        if (isset($this->languageMap[$code])) {
            return $this->languageMap[$code];
        }

        throw new InvalidLanguageCodeException($languageCode, $this->getServiceAlias());
    }
}
