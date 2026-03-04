<?php

declare(strict_types=1);

namespace fork\IbexaThemeTranslationsBundle\Form\Type;

use fork\IbexaThemeTranslationsBundle\Service\LanguageResolverInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class TranslationFilterType extends AbstractType
{
    public function __construct(
        private readonly LanguageResolverInterface $languageResolver,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $languages = $this->languageResolver->getUsedLanguages();
        sort($languages);
        $keys = $languages;
        $values = $languages;
        array_unshift($keys, 'Any');
        array_unshift($values, '');
        $languages = array_combine($keys, $values);
        $builder->add('languageCode', ChoiceType::class, [
            'label' => 'Language Code',
            'required' => false,
            'choices' => $languages,
            'attr' => [
                'styles' => 'margin-right:10px;',
            ],
        ]);

        $builder->add('status', ChoiceType::class, [
            'label' => 'Status',
            'required' => false,
            'choices' => [
                'Any' => '',
                'Missing' => 'missing',
                'Done' => 'done',
                'Pending Approval' => 'pending',
            ],
            'attr' => [
                'styles' => 'margin-right:10px;',
            ],
        ]);

        $builder->add('perPage', ChoiceType::class, [
            'label' => 'Per page',
            'required' => false,
            'choices' => [
                '10' => '10',
                '25' => '25',
                '50' => '50',
                '100' => '100',
            ],
            'attr' => [
                'style' => 'margin-right:10px;',
            ],
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'Filter',
        ]);

        $builder->add('search', TextType::class, [
            'label' => false,
            'required' => false,
            'attr' => [
                'placeholder' => 'Search key or translation…',
                'autocomplete' => 'off',
            ],
        ]);
    }
}
