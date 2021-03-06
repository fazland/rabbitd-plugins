<?php

namespace Fazland\RabbitdPlugins\ErrorHandler;

use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;
use Fazland\Rabbitd\Application\Application;
use Fazland\Rabbitd\Plugin\AbstractPlugin;
use PhpAmqpLib\Connection\AbstractConnection;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ErrorHandlerPlugin extends AbstractPlugin
{
    /**
     * @var bool
     */
    private $enabled = true;

    /**
     * {@inheritdoc}
     */
    public function onStart(ContainerBuilder $container)
    {
        $configuration = $container->getParameter('error_handler');
        $this->enabled = $configuration['enabled'];

        if (! $configuration['enabled']) {
            return;
        }

        $loader = $this->getContainerLoader($container, __DIR__.'/config');
        $loader->load('services.yml');

        $queueName = $configuration['queue'];

        $container->findDefinition('plugins.error_handler.doctrine.entity_mananger')
            ->replaceArgument(0, $configuration['connection']);

        $container->get('plugins.error_handler.doctrine.configuration')
            ->setMetadataDriverImpl($container->get('plugins.error_handler.doctrine.annotations_driver'));

        $queue = isset($container->getParameter('queues')[$queueName]) ? $container->getParameter('queues')[$queueName] : null;
        if (null === $queue) {
            throw new InvalidConfigurationException('Error queue must be specified');
        }

        $container->register('plugins.error_handler.queue.connection', AbstractConnection::class)
            ->setFactory([new Reference('connection_manager'), 'getConnection'])
            ->setArguments([$queue['connection']]);
        $container->setParameter('plugins.error_handler.queue_name', $queue['queue_name']);
    }

    /**
     * {@inheritdoc}
     */
    public function addConfiguration(NodeDefinition $root)
    {
        /** @var ArrayNodeDefinition $root */
        $root
            ->children()
                ->arrayNode('error_handler')
                    ->validate()
                        ->ifTrue(function ($val) {
                            return null === $val['enabled'] && ! empty($val['queue']);
                        })
                        ->then(function ($val) {
                            $val['enabled'] = true;
                            return $val;
                        })
                    ->end()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultNull()->end()
                        ->scalarNode('queue')->isRequired()->end()
                        ->arrayNode('connection')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('driver')->defaultValue('pdo_sqlite')->end()
                                ->scalarNode('url')->info('A URL with connection information; any parameter value parsed from this string will override explicitly set parameters')->end()
                                ->scalarNode('dbname')->end()
                                ->scalarNode('host')->defaultValue('localhost')->end()
                                ->scalarNode('port')->defaultNull()->end()
                                ->scalarNode('user')->defaultValue('root')->end()
                                ->scalarNode('password')->defaultNull()->end()
                                ->scalarNode('charset')->end()
                                ->scalarNode('path')->defaultValue('%application.root_dir%/error_db.sqlite')->end()
                                ->booleanNode('memory')->end()
                                ->scalarNode('unix_socket')->info('The unix socket to use for MySQL')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function registerCommands(Application $application)
    {
        $container = $application->getKernel()->getContainer();
        $configuration = $container->getParameter('error_handler');
        if (! $configuration['enabled']) {
            return;
        }

        $entityManager = $container->get('plugins.error_handler.doctrine.entity_mananger');
        $application->getHelperSet()->set(new ConnectionHelper($entityManager->getConnection()), 'db');
        $application->getHelperSet()->set(new EntityManagerHelper($entityManager), 'em');

        ConsoleRunner::addCommands($application);

        return parent::registerCommands($application);
    }

    public function prependConfiguration(array $configuration)
    {
        if (! isset($configuration['error_handler'])) {
            $configuration['error_handler'] = [];
        }

        $config = $configuration['error_handler'];
        $enabled = isset($config['enabled']) ? $config['enabled'] : ! empty($config['queue']);

        if (! $enabled || ! $config['queue'] || isset($configuration['queues'][$config['queue']])) {
            return [];
        }

        return [
            'queues' => [
                $config['queue'] => [
                    'queue_name' => 'com.fazland.production.error_queue',
                    'exchange' => [
                        'name' => $config['queue'],
                        'type' => 'x-delayed-message',
                        'arguments' => [
                            'x-delayed-type' => 'fanout'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'error-handler';
    }
}
