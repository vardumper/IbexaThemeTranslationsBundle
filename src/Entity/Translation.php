<?php

declare(strict_types=1);

namespace fork\IbexaThemeTranslationsBundle\Entity;

use fork\IbexaThemeTranslationsBundle\FieldType\Translation\Value;
use fork\IbexaThemeTranslationsBundle\Repository\TranslationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: TranslationRepository::class)]
#[ORM\Table(name: 'translation')]
#[ORM\Index(columns: ['language_code', 'trans_key'], name: 'language_code_trans_key_idx')]
#[ORM\Index(columns: ['translation'], name: 'translation_idx')]
#[UniqueEntity(
    fields: ['languageCode', 'transKey'],
    errorPath: 'language_code',
    message: 'This key already exists.',
)]
class Translation implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $languageCode = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $translation = null;

    #[ORM\Column(length: 255)]
    private ?string $transKey = null;

    public function __construct(string $languageCode, string $transKey, ?string $translation = null)
    {
        $this->languageCode = $languageCode;
        $this->transKey = $transKey;
        $this->translation = $translation;
    }

    public static function create(string $languageCode, string $transKey, ?string $translation = null): self
    {
        return new self($languageCode, $transKey, $translation);
    }

    public static function fromFormData(Value $formData): self
    {
        return new self($formData->getLanguageCode(), $formData->getTransKey(), $formData->getTranslation());
    }

    public static function fromArray(array $translation): self
    {
        $entity = new self($translation['languageCode'], $translation['transKey'], $translation['translation']);
        if (isset($translation['id'])) {
            $entity->setId((int) $translation['id']);
        }

        return $entity;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getLanguageCode(): ?string
    {
        return $this->languageCode;
    }

    public function setLanguageCode(string $languageCode): self
    {
        $this->languageCode = $languageCode;

        return $this;
    }

    public function getTranslation(): ?string
    {
        return $this->translation;
    }

    public function setTranslation(?string $translation): self
    {
        $this->translation = $translation;

        return $this;
    }

    public function getTransKey(): ?string
    {
        return $this->transKey;
    }

    public function setTransKey(string $transKey): self
    {
        $this->transKey = $transKey;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'transKey' => $this->transKey,
            'languageCode' => $this->languageCode,
            'translation' => $this->translation,
        ];
    }
}
