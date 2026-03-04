<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\FieldType\Translation;

use Ibexa\Contracts\Core\FieldType\Value as ValueInterface;

final class Value implements ValueInterface
{
    private int $id;
    private ?string $transKey = null;
    private ?string $languageCode = null;
    private ?string $translation = null;

    public function __toString(): string
    {
        return $this->translation ?? '';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getTransKey(): ?string
    {
        return $this->transKey;
    }

    public function setTransKey(?string $transKey): void
    {
        $this->transKey = $transKey;
    }

    public function getLanguageCode(): ?string
    {
        return $this->languageCode;
    }

    public function setLanguageCode(?string $languageCode): void
    {
        $this->languageCode = $languageCode;
    }

    public function getTranslation(): ?string
    {
        return $this->translation;
    }

    public function setTranslation(?string $translation): void
    {
        $this->translation = $translation;
    }
}
