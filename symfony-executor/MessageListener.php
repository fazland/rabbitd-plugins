<?php

namespace Fazland\RabbitdPlugins\SymfonyExecutor;

use Fazland\Rabbitd\Events\Events;
use Fazland\Rabbitd\Events\MessageEvent;
use Fazland\Rabbitd\Util\Silencer;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Process\ProcessBuilder;

class MessageListener implements EventSubscriberInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $symfonyApp;

    public function __construct(LoggerInterface $logger, $symfonyApp)
    {
        $this->logger = $logger;
        $this->symfonyApp = $symfonyApp;
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

        $message = Silencer::call('json_decode', $msg->body, true);
        if (false === $message) {
            $message = Silencer::call('unserialize', $msg->body);
        }

        if (false === $message) {
            return;
        }

        switch ($message['type']) {
            case 'run_process':
                $this->exec($message, $message['cmdline']);
                break;

            case 'run_symfony':
                $cmdline = $message['command'];
                array_unshift($cmdline, $this->symfonyApp);
                $this->exec($message, $cmdline);
                break;

            default:
                return;
        }

        $event->setProcessed();
    }

    /**
     * @param $message
     * @param $cmdline
     *
     * @throws \Exception
     */
    private function exec($message, $cmdline)
    {
        $stdin = isset($message['stdin']) ? $message['stdin'] : null;

        $process = ProcessBuilder::create($cmdline)
            ->setInput(json_encode($stdin))
            ->setTimeout(300)
            ->getProcess();

        $this->logger->info('Executing '.$process->getCommandLine());

        $process->run(function ($type, $data) {
            $this->logger->debug($data);
        });

        if ($process->getExitCode() != 0) {
            $error = 'Process errored [cmd_line: '.$process->getCommandLine().
                ', input: '.json_encode($stdin)."]\n".
                "Output: \n".$process->getOutput()."\n".
                "Error: \n".$process->getErrorOutput();
            $this->logger->error($error);

            throw new \Exception('Process errored. See log for details');
        }
    }
}
