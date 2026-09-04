<?php

namespace Macc\CommonBundle\Form\Extension;

use Override;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Agrega la opción 'wrapper_attr' a todos los tipos */
class WrapperAttrType extends AbstractTypeExtension
{
    #[Override]
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class, ButtonType::class];
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['wrapper_attr' => []]);
        $resolver->setAllowedTypes('wrapper_attr', 'array');
    }

    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars = array_replace($view->vars, [
            'wrapper_attr' => $options['wrapper_attr'],
        ]);
    }
}
