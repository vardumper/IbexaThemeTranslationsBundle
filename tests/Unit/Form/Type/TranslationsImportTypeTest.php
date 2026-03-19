<?php

declare(strict_types=1);

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;
use vardumper\IbexaThemeTranslationsBundle\Form\Type\TranslationsImportType;

class TranslationsImportTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(Validation::createValidator()),
            new PreloadedExtension([new TranslationsImportType()], []),
        ];
    }

    public function testFormHasExpectedFields(): void
    {
        $form = $this->factory->create(TranslationsImportType::class);

        $this->assertTrue($form->has('csv'));
        $this->assertTrue($form->has('mode'));
        $this->assertTrue($form->has('upload'));
    }

    public function testCsvFieldIsFileType(): void
    {
        $form = $this->factory->create(TranslationsImportType::class);

        $this->assertInstanceOf(FileType::class, $form->get('csv')->getConfig()->getType()->getInnerType());
    }

    public function testModeFieldIsChoiceType(): void
    {
        $form = $this->factory->create(TranslationsImportType::class);

        $this->assertInstanceOf(ChoiceType::class, $form->get('mode')->getConfig()->getType()->getInnerType());
    }

    public function testUploadFieldIsSubmitType(): void
    {
        $form = $this->factory->create(TranslationsImportType::class);

        $this->assertInstanceOf(SubmitType::class, $form->get('upload')->getConfig()->getType()->getInnerType());
    }

    public function testModeChoicesContainMergeAndTruncate(): void
    {
        $form = $this->factory->create(TranslationsImportType::class);
        $choices = $form->get('mode')->getConfig()->getOption('choices');

        $this->assertContains('merge', $choices);
        $this->assertContains('truncate', $choices);
    }

    public function testModeIsExpandedSingleChoice(): void
    {
        $form = $this->factory->create(TranslationsImportType::class);
        $config = $form->get('mode')->getConfig();

        $this->assertTrue($config->getOption('expanded'));
        $this->assertFalse($config->getOption('multiple'));
    }
}
