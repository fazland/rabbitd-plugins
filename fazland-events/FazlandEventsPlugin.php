<?php

namespace Fazland\RabbitdPlugins\FazlandEvents;

use Fazland\Rabbitd\Plugin\AbstractPlugin;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class FazlandEventsPlugin extends AbstractPlugin
{
    /**
     * {@inheritdoc}
     */
    public function onStart(ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/config'));
        $loader->load('services.yml');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'fazland-events';
    }
}
