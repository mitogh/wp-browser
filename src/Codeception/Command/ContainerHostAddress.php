<?php
/**
 * The command to fetch and returns the container host IP address, or hostname, from the perspective of the container.
 *
 * @package Codeception\Command
 */


namespace Codeception\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\LogicException;
use tad\WPBrowser\Command\CustomCommand;
use tad\WPBrowser\Environment\OperatingSystem;

/**
 * Class ContainerHostAddress
 *
 * @package Codeception\Command
 */
class ContainerHostAddress extends CustomCommand
{
    /**
     * {@inheritDoc}
     */
    public static function getCommandName()
    {
        return 'container:host-address';
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->setDescription('Returns the IP adddress, or host name, of the host machine.');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        echo $this->getOutput($input, $output) . PHP_EOL;
    }

    /**
     * Returns the command output, if any.
     *
     * @param InputInterface       $input  The current input.
     * @param OutputInterface|null $output The current output instance, if any.
     *
     * @return string The command output, if any.
     */
    public function getOutput(InputInterface $input, OutputInterface $output = null)
    {
        $osFamily = $this->commandSupport->getOperatingSystemFamily();
        if (in_array($osFamily, [ OperatingSystem::MAC, OperatingSystem::WINDOWS ], true)) {
            return 'host.docker.internal';
        }

        $process = $this->getProcess($input, $output);
        $process->mustRun();

        $rawOutput = $process->getOutput();

        preg_match('~inet (?<ip>[\\d+.]+)~', $rawOutput, $matches);

        if (empty($matches['ip'])) {
            throw  new LogicException(
                "Output does not contain pattern of an IP address: {$rawOutput}"
            );
        }

        $ipAddress = $matches['ip'];

        return $ipAddress;
    }

    /**
     * {@inheritDoc}
     */
    public function getProcess(InputInterface $input, OutputInterface $output = null)
    {
        return $this->commandSupport->getProcessForCommand((array) $this->getCommandLine($input, $output));
    }

    /**
     * {@inheritDoc}
     */
    public function getCommandLine(InputInterface $input, OutputInterface $output = null)
    {
        return [ 'ip', '-4', 'addr', 'show', 'docker0' ];
    }
}
