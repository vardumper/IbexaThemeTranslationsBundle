<?php

declare(strict_types=1);

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;
use vardumper\IbexaThemeTranslationsBundle\Form\Type\TranslationFilterType;
use vardumper\IbexaThemeTranslationsBundle\Service\LanguageResolverInterface;

class TranslationFilterTypeTest extends TypeTestCase
{
    private LanguageResolverInterface $resolver;

    protected function setUp(): void
    {
        $this->resolver = $this->createMock(LanguageResolverInterface::class);
        $this->resolver->method('getUsedLanguages')->willReturn(['eng-GB', 'deu-DE']);

        parent::setUp();
    }

    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(Validation::createValidator()),
            new PreloadedExtension([new TranslationFilterType($this->resolver)], []),
        ];
    }

    public function testFormHasExpectedFields(): void
    {
        $form = $this->factory->create(TranslationFilterType::class);

        $this->assertTrue($form->has('languageCode'));
        $this->assertTrue($form->has('status'));
        $this->assertTrue($form->has('perPage'));
        $this->assertTrue($form->has('submit'));
        $this->assertTrue($form->has('search'));
    }

    public function testLanguageCodeIsChoiceType(): void
    {
        $form = $this->factory->create(TranslationFilterType::class);

        $this->assertInstanceOf(ChoiceType::class, $form->get('languageCode')->getConfig()->getType()->getInnerType());
    }

    public function testLanguageCodeChoicesContainResolvedLanguages(): void
    {
        $form = $this->factory->create(TranslationFilterType::class);
        $choices = $form->get('languageCode')->getConfig()->getOption('choices');

        // Values should contain the resolved language codes
        $this->assertContains('eng-GB', $choices);
        $this->assertContains('deu-DE', $choices);
        // 'Any' key maps to empty string
        $this->assertContains('', $choices);
    }

    public function testStatusChoicesContainExpectedValues(): void
    {
        $form = $this->factory->create(TranslationFilterType::class);
        $choices = $form->get('status')->getConfig()->getOption('choices');

        $this->assertContains('', $choices);
        $this->assertContains('missing', $choices);
        $this->assertContains('done', $choices);
        $this->assertContains('pending', $choices);
    }

    public function testPerPageChoicesContainExpectedValues(): void
    {
        $form = $this->factory->create(TranslationFilterType::class);
        $choices = $form->get('perPage')->getConfig()->getOption('choices');

        $this->assertContains('10', $choices);
        $this->assertContains('25', $choices);
        $this->assertContains('50', $choices);
        $this->assertContains('100', $choices);
    }

    public function testSubmitIsSubmitType(): void
    {
        $form = $this->factory->create(TranslationFilterType::class);

        $this->assertInstanceOf(SubmitType::class, $form->get('submit')->getConfig()->getType()->getInnerType());
    }

    public function testSearchIsTextTypeAndNotRequired(): void
    {
        $form = $this->factory->create(TranslationFilterType::class);
        $config = $form->get('search')->getConfig();

        $this->assertInstanceOf(TextType::class, $config->getType()->getInnerType());
        $this->assertFalse($config->getRequired());
    }
}
