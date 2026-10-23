<?php

namespace Macc\CommonBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class MaccCommonBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // This tells Symfony to load your bundle's services.yaml
        $container->import('../config/services.yaml');
    }

    /**
     * Registrar form theme y namespace `@Macc`, para los componentes `<twig:Macc:Nombre>`
     */
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('twig')) {
            return;
        }

        $container->extension('twig', [
            'form_themes' => ['@MaccCommon/form/macc_form_theme.html.twig'],
            'paths' => [$this->getPath().'/templates' => 'Macc'],
        ]);
    }
}
