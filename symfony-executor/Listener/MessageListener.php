<?php

namespace Fazland\RabbitdPlugins\SymfonyExecutor\Listener;

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

        $body = $msg->getBody();
        $message = Silencer::call('json_decode', $body, true);
        if (null === $message) {
            $message = Silencer::call('unserialize', $body);
        }

        if (false === $message) {
            return;
        }

        $stdin = isset($message['stdin']) ? $message['stdin'] : null;
        switch ($message['type']) {
            case 'run_process':
                $process = $this->processBuilder->buildProcess($message['cmdline'], $stdin);
                break;

            case 'run_symfony':
                $process = $this->processBuilder->getSymfonyProcess($message['command'], $stdin);
                break;

            default:
                return;
        }

        $this->processExecutor->execute($process);
        $event->setProcessed();
    }
}
