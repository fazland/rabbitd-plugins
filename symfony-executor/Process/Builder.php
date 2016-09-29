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
    }

    public function getSymfonyProcess(array $arguments, $stdin = null)
    {
        array_unshift($arguments, $this->symfonyApp);

        return $this->buildProcess($arguments, $stdin);
    }

    /**
     * @param array $arguments
     * @param null $stdin
     *
     * @return Process
     */
    public function buildProcess(array $arguments, $stdin = null)
    {
        $builder = SymfonyBuilder::create($arguments);
        $builder->setTimeout(300);

        if (null !== $stdin) {
            $builder->setInput(json_encode($stdin));
        }

        return $builder->getProcess();
    }
}
