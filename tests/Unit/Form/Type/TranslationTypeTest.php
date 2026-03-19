<?php

declare(strict_types=1);

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;
use vardumper\IbexaThemeTranslationsBundle\FieldType\Translation\Value;
use vardumper\IbexaThemeTranslationsBundle\Form\Type\TranslationType;
use vardumper\IbexaThemeTranslationsBundle\Service\LanguageResolverInterface;

class TranslationTypeTest extends TypeTestCase
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
            new PreloadedExtension([new TranslationType($this->resolver)], []),
        ];
    }

    public function testFormHasExpectedFields(): void
    {
        $form = $this->factory->create(TranslationType::class);

        $this->assertTrue($form->has('transKey'));
        $this->assertTrue($form->has('languageCode'));
        $this->assertTrue($form->has('translation'));
    }

    public function testTransKeyIsTextType(): void
    {
        $form = $this->factory->create(TranslationType::class);

        $this->assertInstanceOf(TextType::class, $form->get('transKey')->getConfig()->getType()->getInnerType());
    }

    public function testTransKeyHasNotBlankConstraint(): void
    {
        $form = $this->factory->create(TranslationType::class);
        $constraints = $form->get('transKey')->getConfig()->getOption('constraints');
        $types = array_map(fn ($c) => get_class($c), $constraints);

        $this->assertContains(NotBlank::class, $types);
    }

    public function testTransKeyHasLengthConstraint(): void
    {
        $form = $this->factory->create(TranslationType::class);
        $constraints = $form->get('transKey')->getConfig()->getOption('constraints');

        $lengthConstraints = array_filter($constraints, fn ($c) => $c instanceof Length);
        $this->assertNotEmpty($lengthConstraints);

        $lengthConstraint = array_values($lengthConstraints)[0];
        $this->assertSame(3, $lengthConstraint->min);
        $this->assertSame(255, $lengthConstraint->max);
    }

    public function testLanguageCodeIsChoiceType(): void
    {
        $form = $this->factory->create(TranslationType::class);

        $this->assertInstanceOf(ChoiceType::class, $form->get('languageCode')->getConfig()->getType()->getInnerType());
    }

    public function testLanguageCodeChoicesMatchResolvedLanguages(): void
    {
        $form = $this->factory->create(TranslationType::class);
        $choices = $form->get('languageCode')->getConfig()->getOption('choices');

        $this->assertArrayHasKey('eng-GB', $choices);
        $this->assertArrayHasKey('deu-DE', $choices);
        $this->assertSame('eng-GB', $choices['eng-GB']);
        $this->assertSame('deu-DE', $choices['deu-DE']);
    }

    public function testLanguageCodeHasNotBlankConstraint(): void
    {
        $form = $this->factory->create(TranslationType::class);
        $constraints = $form->get('languageCode')->getConfig()->getOption('constraints');
        $types = array_map(fn ($c) => get_class($c), $constraints);

        $this->assertContains(NotBlank::class, $types);
    }

    public function testTranslationIsTextType(): void
    {
        $form = $this->factory->create(TranslationType::class);

        $this->assertInstanceOf(TextType::class, $form->get('translation')->getConfig()->getType()->getInnerType());
    }

    public function testDataClassIsValue(): void
    {
        $form = $this->factory->create(TranslationType::class);

        $this->assertSame(Value::class, $form->getConfig()->getOption('data_class'));
    }
}
