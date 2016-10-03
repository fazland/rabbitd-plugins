<?php

namespace Fazland\RabbitdPlugins\ErrorHandler;

use Doctrine\ORM\EntityManager;
use Fazland\Rabbitd\Events\ChildStartEvent;
use Fazland\Rabbitd\Events\Events;
use Fazland\Rabbitd\Events\MessageEvent;
use Fazland\Rabbitd\Exception\MessageUnprocessedException;
use Fazland\Rabbitd\Message\AMQPMessage;
use Fazland\Rabbitd\Message\MessageInterface;
use Fazland\Rabbitd\Queue\QueueInterface;
use Fazland\Rabbitd\Util\Silencer;
use Fazland\RabbitdPlugins\ErrorHandler\Entity\Message;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ErrorMessageHolder implements EventSubscriberInterface
{
    use ErrorMessageGeneratorTrait;

    /**
     * @var \SplObjectStorage|MessageInterface[]
     */
    private $storage;

    /**
     * @var QueueInterface
     */
    private $errorQueue;

    /**
     * @var EntityManager
     */
    private $entityManager;
    /**
     * @var
     */
    private $queueName;

    public function __construct($queueName, EntityManager $entityManager)
    {
        $this->storage = new \SplObjectStorage();
        $this->entityManager = $entityManager;
        $this->queueName = $queueName;
    }

    public function addMessage(MessageInterface $message)
    {
        $this->storage->attach($message);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            Events::CHILD_EVENT_LOOP => 'onLoop',
            Events::CHILD_START => 'onChildStart',
        ];
    }

    public function onChildStart(ChildStartEvent $event)
    {
        $queue = $event->getChild()->getQueue();
        if ($queue->getName() === $this->queueName) {
            $this->errorQueue = $queue;
        }
    }

    public function onLoop($event, $eventName, EventDispatcherInterface $eventDispatcher)
    {
        if (null === $this->errorQueue) {
            return;
        }

        foreach ($this->storage as $message) {
            if ($this->process($message, $eventDispatcher)) {
                $this->storage->detach($message);
            }
        }
    }

    private function process(MessageInterface $message, EventDispatcherInterface $dispatcher)
    {
        $body = Silencer::call('json_decode', $message->getBody(), true);
        if (!$body || $body['type'] !== 'faz_errored_message') {
            $message->sendAcknowledged();

            return true;
        }

        if ($body['next'] > time()) {
            return false;
        }

        try {
            $wrappedMsg = new AMQPMessage(Silencer::call('unserialize', $body['message']));
            $dispatcher->dispatch(Events::MESSAGE_RECEIVED, $event = new MessageEvent($wrappedMsg));

            if (! $event->isProcessed()) {
                throw new MessageUnprocessedException($wrappedMsg);
            }
        } catch (\Exception $e) {
            $attempt = $body['attempt'];
            switch ($attempt) {
                case 1:
                    $this->errorQueue->publishMessage($this->generateBodyFor($message, 2));
                    break;

                case 2:
                    $this->errorQueue->publishMessage($this->generateBodyFor($message, 3));
                    break;

                case 3:
                    $error = new Message();
                    $error->setBody(json_encode($body));

                    $this->entityManager->persist($error);
                    $this->entityManager->flush();
                    break;
            }
        }

        $message->sendAcknowledged();

        return true;
    }
}
