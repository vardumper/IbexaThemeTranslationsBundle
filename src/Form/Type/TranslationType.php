<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use vardumper\IbexaThemeTranslationsBundle\FieldType\Translation\Value;
use vardumper\IbexaThemeTranslationsBundle\Service\LanguageResolverInterface;

final class TranslationType extends AbstractType
{
    public function __construct(
        private readonly LanguageResolverInterface $languageResolver,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('transKey', TextType::class, [
            'required' => true,
            'label' => 'Key',
            'attr' => [
                'class' => 'form-control',
            ],
            'help' => 'Enter the key for the translation.',
            'help_attr' => [
                'class' => 'help-text',
            ],
            'error_bubbling' => true,
            'invalid_message' => 'The key you entered is invalid.',
            'constraints' => [
                new NotBlank(),
                new Length([
                    'min' => 3,
                    'max' => 255,
                ]),
            ],
        ]);

        $languages = $this->languageResolver->getUsedLanguages();
        $languages = array_combine($languages, $languages);

        $builder->add('languageCode', ChoiceType::class, [
            'required' => true,
            'label' => 'Language Code',
            'attr' => [
                'class' => 'form-control',
            ],
            'help' => 'Choose a Language Code for this translation.',
            'help_attr' => [
                'class' => 'help-text',
            ],
            'error_bubbling' => true,
            'invalid_message' => 'The Language Code you entered is invalid.',
            'choices' => $languages,
            'constraints' => [
                new NotBlank(),
            ],
        ]);
        $builder->add('translation', TextType::class, [
            'required' => true,
            'label' => 'Translation',
            'attr' => [
                'class' => 'form-control',
            ],
            'help' => 'The actual translation.',
            'help_attr' => [
                'class' => 'help-text',
            ],
            'error_bubbling' => true,
            'invalid_message' => 'The Translation contains invalid characters.',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Value::class,
        ]);
    }
}
