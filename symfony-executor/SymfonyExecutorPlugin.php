<?php

namespace Fazland\RabbitdPlugins\SymfonyExecutor;

use Fazland\Rabbitd\Plugin\AbstractPlugin;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class SymfonyExecutorPlugin extends AbstractPlugin
{
    /**
     * {@inheritdoc}
     */
    public function addConfiguration(NodeDefinition $root)
    {
        $root
            ->children()
                ->scalarNode('symfony_app')
                    ->defaultValue('/var/www/symfony/app/console')
                ->end()
            ->end()
        ;
    }

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
        return 'symfony-executor';
    }
}
