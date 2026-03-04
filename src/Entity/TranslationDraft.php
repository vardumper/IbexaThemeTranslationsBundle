<?php

declare(strict_types=1);

namespace fork\IbexaThemeTranslationsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use fork\IbexaThemeTranslationsBundle\Repository\TranslationDraftRepository;

#[ORM\Entity(repositoryClass: TranslationDraftRepository::class)]
#[ORM\Table(name: 'translation_draft')]
#[ORM\UniqueConstraint(name: 'draft_language_code_trans_key_idx', columns: ['language_code', 'trans_key'])]
class TranslationDraft
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $languageCode;

    #[ORM\Column(length: 255)]
    private string $transKey;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $translation = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $languageCode, string $transKey, ?string $translation = null)
    {
        $this->languageCode = $languageCode;
        $this->transKey = $transKey;
        $this->translation = $translation;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLanguageCode(): string
    {
        return $this->languageCode;
    }

    public function getTransKey(): string
    {
        return $this->transKey;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
