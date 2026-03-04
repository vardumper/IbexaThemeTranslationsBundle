<?php

declare(strict_types=1);

use Ibexa\Contracts\ContentForms\Data\Content\FieldData;
use Ibexa\Contracts\Core\FieldType\ValueSerializerInterface;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use vardumper\IbexaThemeTranslationsBundle\FieldType\Translation\Type;
use vardumper\IbexaThemeTranslationsBundle\Form\Type\TranslationType;

uses(PHPUnit\Framework\TestCase::class);

it('returns the field type identifier', function () {
    $serializer = $this->createMock(ValueSerializerInterface::class);
    $validator = $this->createMock(ValidatorInterface::class);
    $type = new Type($serializer, $validator);

    expect($type->getFieldTypeIdentifier())->toBe('translation');
});

it('adds a value child to the form with required and label from field definition', function () {
    $fieldDefinition = $this->getMockBuilder(FieldDefinition::class)
        ->disableOriginalConstructor()
        ->getMock();
    $fieldDefinition->method('getName')->willReturn('My Translation Label');

    $fieldData = new FieldData(['fieldDefinition' => $fieldDefinition]);

    $fieldForm = $this->createMock(FormInterface::class);
    $fieldForm->expects($this->once())
        ->method('add')
        ->with(
            'value',
            TranslationType::class,
            $this->callback(static function (array $options) {
                return array_key_exists('required', $options) && ($options['label'] ?? null) === 'My Translation Label';
            })
        );

    $serializer = $this->createMock(ValueSerializerInterface::class);
    $validator = $this->createMock(ValidatorInterface::class);
    $type = new Type($serializer, $validator);
    $type->mapFieldValueForm($fieldForm, $fieldData);
});
