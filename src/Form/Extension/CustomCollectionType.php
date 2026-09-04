<?php

namespace Macc\CommonBundle\Form\Extension;

use Override;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomCollectionType extends AbstractTypeExtension
{
    #[Override]
    public static function getExtendedTypes(): iterable
    {
        return [CollectionType::class];
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'wrapper_class' => 'card',
            'add_button_label' => 'ítem',
            'container_class' => '',
            'error_bubbling' => false,
            'prototype_name' => static function (Options $options, ?string $value): string {
                return '__proto_' . bin2hex(random_bytes(4)) . '__';
            },
        ]);
    }

    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars = array_replace($view->vars, [
            'wrapper_class' => $options['wrapper_class'],
            'add_button_label' => $options['add_button_label'],
            'container_class' => $options['container_class'],
            'prototype_name' => $options['prototype_name'],
        ]);
    }
}
