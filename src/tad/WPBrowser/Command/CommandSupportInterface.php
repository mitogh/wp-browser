<?php
/**
 * ${CARET}
 *
 * @since   TBD
 *
 * @package tad\WPBrowser\Command
 */


namespace tad\WPBrowser\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use tad\WPBrowser\Environment\OperatingSystem;

interface CommandSupportInterface
{

    /**
     * Returns the operating system family.
     *
     * @return mixed
     *
     * @uses  OperatingSystem::getFamily()
     */
    public function getOperatingSystemFamily();

    /**
     * Returns an instance of the Symfony/Process/Process class for the specified command line.
     *
     * @param array          $commandLine The command in the format supported by the Symfony/Process/Process component.
     * @param string|null    $cwd         The current working directory path.
     * @param array|null     $env         An associative array specifying the environment that will be set for the
     *                                    process.
     * @param mixed|null     $input       The input as stream resource, scalar or \Traversable, or null for no input.
     * @param int|float|null $timeout     The timeout in seconds or null to disable.
     *
     * @return Process The built process, ready to run.
     *
     * @uses  \tad\WPBrowser\Adapters\Process::forCommand()
     */
    public function getProcessForCommand(
        array $commandLine,
        $cwd = null,
        array $env = null,
        $input = null,
        $timeout = null
    );

    /**
     * Returns an instance of a wp-browser command, built using the provided context.
     *
     * @param string               $commandClass The class of the command to build.
     * @param InputInterface $input The command input.
     * @param OutputInterface $output The command output, if any.
     *
     * @return Process  The command process, ready to run.
     */
    public function getCommandProcess($commandClass, InputInterface $input, OutputInterface $output = null);

    /**
     * Executes a command and returns its output.
     *
     * @param string         $commandClass The class of the command to build.
     * @param InputInterface $input The command input.
     * @param OutputInterface|null $output The command output, if any.
     *
     * @return string The command output, if any.
     */
    public function getCommandOutput($commandClass, InputInterface$input, OutputInterface $output = null);
}
