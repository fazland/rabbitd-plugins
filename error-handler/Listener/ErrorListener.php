<?php

namespace Fazland\RabbitdPlugins\ErrorHandler\Listener;

use Fazland\Rabbitd\Events\Events;
use Fazland\Rabbitd\Events\MessageEvent;
use Fazland\Rabbitd\Exception\MessageException;
use Fazland\Rabbitd\Queue\AmqpLibQueue;
use Fazland\Rabbitd\Queue\QueueInterface;
use Fazland\Rabbitd\Util\Silencer;
use Fazland\RabbitdPlugins\ErrorHandler\ErrorMessageGeneratorTrait;
use Fazland\RabbitdPlugins\ErrorHandler\ErrorMessageHolder;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleExceptionEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ErrorListener implements EventSubscriberInterface
{
    use ErrorMessageGeneratorTrait;
    use LoggerAwareTrait;

    /**
     * @var QueueInterface
     */
    private $queue = null;

    /**
     * @var ErrorMessageHolder
     */
    private $holder;

    /**
     * @var string
     */
    private $queueName;

    /**
     * @var AMQPStreamConnection
     */
    private $connection;

    public function __construct(AMQPStreamConnection $connection, $queueName, ErrorMessageHolder $holder)
    {
        $this->holder = $holder;
        $this->queueName = $queueName;
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            ConsoleEvents::EXCEPTION => 'onException',
            Events::MESSAGE_RECEIVED => 'onMessage',
        ];
    }

    public function onException(ConsoleExceptionEvent $event, $eventName, EventDispatcherInterface $eventDispatcher)
    {
        $exception = $event->getException();
        if (! $exception instanceof MessageException) {
            return;
        }

        $amqpMessage = $exception->getQueueMessage();

        if (null === $this->queue) {
            if (! $this->connection->isConnected()) {
                $this->connection->reconnect();
            }

            $this->queue = new AmqpLibQueue($this->logger, $this->connection, $this->queueName, $eventDispatcher);
        }

        $this->queue->publishMessage($this->generateBodyFor($amqpMessage));
        $amqpMessage->sendAcknowledged();
    }

    public function onMessage(MessageEvent $event)
    {
        $msg = $event->getMessage();

        $body = Silencer::call('json_decode', $msg->getBody(), true);
        if (!$body || $body['type'] !== 'faz_errored_message') {
            return;
        }

        $this->holder->addMessage($msg);

        $msg->setNeedAck(false);
        $event->setProcessed();

        sleep(10);
    }
}
