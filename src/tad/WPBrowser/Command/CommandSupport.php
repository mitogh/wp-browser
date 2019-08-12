<?php
/**
 * A facade for a collection of distinguished command utilities and support classes to ease dependency injection.
 *
 * @package tad\WPBrowser\Command
 */


namespace tad\WPBrowser\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use tad\WPBrowser\Adapters\Process;
use tad\WPBrowser\Environment\OperatingSystem;

/**
 * Class CommandSupport
 *
 * @package tad\WPBrowser\Command
 */
class CommandSupport implements CommandSupportInterface
{
    /**
     * An instance of the operating system adapter.
     *
     * @var OperatingSystem
     */
    protected $operatingSystem;

    /**
     * An instance of the Process builder adapter.
     *
     * @var Process
     */
    protected $processBuilder;

    /**
     * An instance of the Command factory.
     *
     * @var CommandFactory
     */
    protected $commandFactory;

    /**
     * {@inheritDoc}
     */
    public function getOperatingSystemFamily()
    {
        $this->operatingSystem = $this->operatingSystem ?: new OperatingSystem();

        return $this->operatingSystem->getFamily();
    }

    /**
     * {@inheritDoc}
     */
    public function getProcessForCommand(
        array $commandLine,
        $cwd = null,
        array $env = null,
        $input = null,
        $timeout = null
    ) {
        $this->processBuilder = $this->processBuilder ?: new Process();

        return $this->processBuilder->forCommand($commandLine, $cwd, $env, $input, $timeout);
    }

    /**
     * {@inheritDoc}
     */
    public function getCommandProcess($commandClass, InputInterface $input, OutputInterface $output = null)
    {
        $this->commandFactory = $this->commandFactory ?: new CommandFactory();
        $command              = $this->commandFactory->buildCustomCommand($commandClass);

        return $command->getProcess($input, $output);
    }

    /**
     * {@inheritDoc}
     */
    public function getCommandOutput($commandClass, InputInterface $input, OutputInterface $output = null)
    {
        $this->commandFactory = $this->commandFactory ?: new CommandFactory();
        $command              = $this->commandFactory->buildCustomCommand($commandClass);

        return $command->getOutput($input, $output);
    }
}
