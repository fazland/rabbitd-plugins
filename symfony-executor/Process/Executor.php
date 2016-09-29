<?php

namespace Fazland\RabbitdPlugins\SymfonyExecutor\Process;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class Executor
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function execute(Process $process)
    {
        $this->logger->info('Executing '.$process->getCommandLine());

        $process->run(function ($type, $data) {
            $this->logger->debug($data);
        });

        if ($process->getExitCode() != 0) {
            $error = 'Process errored [cmd_line: '.$process->getCommandLine().
                ', input: '.$process->getInput()."]\n".
                "Output: \n".$process->getOutput()."\n".
                "Error: \n".$process->getErrorOutput();
            $this->logger->error($error);

            throw new \Exception('Process errored. See log for details');
        }
    }
}
