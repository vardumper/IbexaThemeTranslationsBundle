<?php

declare(strict_types=1);

namespace fork\IbexaThemeTranslationsBundle\FieldType\Translation;

use fork\IbexaThemeTranslationsBundle\Form\Type\TranslationType;
use Ibexa\Contracts\ContentForms\Data\Content\FieldData;
use Ibexa\Contracts\ContentForms\FieldType\FieldValueFormMapperInterface;
use Ibexa\Contracts\Core\FieldType\Generic\Type as GenericType;
use Symfony\Component\Form\FormInterface;

final class Type extends GenericType implements FieldValueFormMapperInterface
{
    private const FIELD_TYPE_IDENTIFIER = 'translation';

    public function getFieldTypeIdentifier(): string
    {
        return self::FIELD_TYPE_IDENTIFIER;
    }

    public function mapFieldValueForm(FormInterface $fieldForm, FieldData $data)
    {
        $definition = $data->getFieldDefinition();
        $fieldForm->add('value', TranslationType::class, [
            'required' => $definition->isRequired,
            'label' => $definition->getName(),
        ]);
    }
}
