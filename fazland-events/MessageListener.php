<?php

namespace Fazland\RabbitdPlugins\FazlandEvents;

use Fazland\Rabbitd\Events\Events;
use Fazland\Rabbitd\Events\MessageEvent;
use Fazland\Rabbitd\Util\Silencer;
use Fazland\RabbitdPlugins\SymfonyExecutor\Process\Builder;
use Fazland\RabbitdPlugins\SymfonyExecutor\Process\Executor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MessageListener implements EventSubscriberInterface
{
    /**
     * @var Builder
     */
    private $processBuilder;

    /**
     * @var Executor
     */
    private $processExecutor;

    public function __construct(Builder $processBuilder, Executor $processExecutor)
    {
        $this->processBuilder = $processBuilder;
        $this->processExecutor = $processExecutor;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            Events::MESSAGE_RECEIVED => 'onMessage',
        ];
    }

    public function onMessage(MessageEvent $event)
    {
        $msg = $event->getMessage();

        $message = Silencer::call('json_decode', $msg->getBody(), true);
        if (false === $message || $message['type'] !== 'event') {
            return;
        }

        if (! isset($message['args'])) {
            $event->setProcessed();

            return;
        }

        $message['args'] = Silencer::call('json_decode', $message['args'], true);
        if (false === $message['args'] || ! isset($message['args']['event_type'])) {
            $event->setProcessed();

            return;
        }

        $event_type = $message['args']['event_type'];
        unset($message['args']['event_type']);

        $arguments = ['fazland:event', $event_type, json_encode($message['args'])];
        $process = $this->processBuilder->getSymfonyProcess($arguments);

        $this->processExecutor->execute($process);
        $event->setProcessed();
    }
}
