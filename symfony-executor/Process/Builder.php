<?php

namespace Fazland\RabbitdPlugins\SymfonyExecutor\Process;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder as SymfonyBuilder;

class Builder
{
    /**
     * @var string
     */
    private $symfonyApp;

    /**
     * @param string $symfonyApp
     */
    public function __construct($symfonyApp)
    {
        $this->symfonyApp = $symfonyApp;
        $this->argMax = DIRECTORY_SEPARATOR === '\\' ? 32767 : (int)`getconf ARG_MAX`;
    }

    public function getSymfonyProcess(array $arguments, $stdin = null)
    {
        array_unshift($arguments, $this->symfonyApp);

        $process = $this->buildProcess($arguments, $stdin);
        $process->setWorkingDirectory(dirname($this->symfonyApp));

        return $process;
    }

    /**
     * @param array $arguments
     * @param null $stdin
     *
     * @return Process
     */
    public function buildProcess(array $arguments, $stdin = null)
    {
        foreach ($arguments as $k => $arg) {
            if (strlen($arg) >= $this->argMax) {
                throw new \RuntimeException('Argument '.$k.' exceeds allowed size of '.$this->argMax.'. Aborting execution...');
            }
        }

        $builder = SymfonyBuilder::create($arguments);
        $builder->setTimeout(300);

        if (null !== $stdin) {
            $builder->setInput(json_encode($stdin));
        }

        return $builder->getProcess();
    }
}
