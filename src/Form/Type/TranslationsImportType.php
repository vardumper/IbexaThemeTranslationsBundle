<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;

final class TranslationsImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('csv', FileType::class, [
            'label' => 'Choose a CSV file',
            'mapped' => false,
            'required' => true,
            'multiple' => false,
            'constraints' => [
                new File([
                    'maxSize' => '10m',
                ]),
            ],
        ]);

        $builder->add('mode', ChoiceType::class, [
            'label' => 'Import Mode',
            'choices' => [
                'Keep existing translations (updates existing translations, adds new ones)' => 'merge',
                'Empty table prior to import (only keep what\'s in the CSV)' => 'truncate',
            ],
            'multiple' => false,
            'expanded' => true,
        ]);

        $builder->add('upload', SubmitType::class, [
            'label' => 'Import',
        ]);
    }
}
