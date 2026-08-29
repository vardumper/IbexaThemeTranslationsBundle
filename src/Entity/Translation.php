<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use vardumper\IbexaThemeTranslationsBundle\FieldType\Translation\Value;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationRepository;

#[ORM\Entity(repositoryClass: TranslationRepository::class)]
#[ORM\Table(name: 'translation')]
// DB-level uniqueness (see flex/recipe/migrations/Version20260828073000.php) — the validator alone
// cannot prevent duplicates created by concurrent requests.
#[ORM\UniqueConstraint(name: 'translation_language_code_trans_key_uniq', columns: ['language_code', 'trans_key'])]
#[ORM\Index(columns: ['translation'], name: 'translation_idx')]
#[UniqueEntity(
    fields: ['languageCode', 'transKey'],
    errorPath: 'transKey',
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
        if (!isset($translation['languageCode'], $translation['transKey']) || $translation['languageCode'] === '' || $translation['transKey'] === '') {
            throw new \InvalidArgumentException('Translation array must contain non-empty languageCode and transKey.');
        }

        $entity = new self(
            (string) $translation['languageCode'],
            (string) $translation['transKey'],
            isset($translation['translation']) ? (string) $translation['translation'] : null,
        );
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
