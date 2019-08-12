<?php
/**
 * Runs Codeception in containers.
 *
 * @package Codeception\Command
 */

namespace Codeception\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process as SymfonyProcess;
use tad\WPBrowser\Command\CustomCommand;

/**
 * Class ContainerRun
 *
 * @package Codeception\Command
 */
class ContainerRun extends CustomCommand
{

    /**
     * Returns the name of the command.
     *
     * @return string The command name.
     */
    public static function getCommandName()
    {
        return 'container:run';
    }

    /**
     * Handles the command output.
     *
     * @param string $type   The command output type, one of `Symfony\Component\Process\Process` output constants.
     * @param string $buffer The new output received from the running command.
     */
    public function handleOutput($type, $buffer)
    {
        if ($type === SymfonyProcess::ERR) {
            echo 'ERR > ' . $buffer;
        } else {
            echo $buffer;
        }
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
        $process = $this->getProcess($input, $output);
        $process->mustRun();

        return $process->getOutput();
    }

    /**
     * Returns the command Symfony Process, ready to run.
     *
     * @param InputInterface  $input  The current input.
     * @param OutputInterface $output The current output, if any.
     *
     * @return SymfonyProcess The command Symfony process, ready to run.
     */
    public function getProcess(InputInterface $input, OutputInterface $output = null)
    {
        $command = $this->getCommandLine($input, $output);

        $env = [];
        if (isset($_SERVER['PATH'])) {
            $env['PATH'] = $_SERVER['PATH'];
        }

        return $this->commandSupport->getProcessForCommand($command, codecept_root_dir(), $env);
    }

    /**
     * Returns the command line, in array format, the command would run.
     *
     * @param InputInterface       $input  The current input.
     * @param OutputInterface|null $output The current output.
     *
     * @return array The process command line, in array format.
     */
    public function getCommandLine(InputInterface $input, OutputInterface $output = null)
    {
        $dockerComposeBin      = 'docker-compose';
        $dockerComposeCommand  = 'run';
        $dockerComposeOptions  = [ '--rm' ];
        $containerName         = $input->hasOption('container-name') ?
            $input->getOption('container-name')
            : 'wpbrowser';
        $suite                 = $input->hasArgument('suite') ? $input->getArgument('suite') : 'unit';
        $codeceptArgs          = "run {$suite}";
        $codeceptOutputOptions = $this->parseOutputOptions($output);

        $hostAddress            = $this->commandSupport->getCommandOutput(
            ContainerHostAddress::class,
            $input,
            $output
        );
        $dockerComposeOptions[] = '-e';
        $dockerComposeOptions[] = 'XDEBUG_REMOTE_HOST=' . $hostAddress;

        $commandLine   = [];
        $commandLine[] = $dockerComposeBin;
        $commandLine[] = $dockerComposeCommand;
        $commandLine   = array_merge($commandLine, array_map('trim', $dockerComposeOptions));
        $commandLine[] = $containerName;
        $commandLine[] = $codeceptArgs . $codeceptOutputOptions;

        return $commandLine;
    }

    /**
     * Parses the output options and returns the output flags that should be forwarded to the Codeception binary.
     *
     * @param OutputInterface|null $output The currently used output.
     *
     * @return string The output options and flags, if any.
     */
    protected function parseOutputOptions(OutputInterface $output = null)
    {
        if (null === $output) {
            return '';
        }

        $options = [];

        if ($verbosity = $output->getVerbosity()) {
            switch ($verbosity) {
                case OutputInterface::VERBOSITY_QUIET:
                    $options[] = '-q';
                    break;
                case OutputInterface:: VERBOSITY_DEBUG:
                    $options[] = '--debug';
                    break;
                case OutputInterface::VERBOSITY_NORMAL:
                    break;
                default:
                    $count     = min(3, $verbosity / 32);
                    $options[] = '-' . implode('', array_fill(0, $count, 'v'));
                    break;
            }
        }

        return count($options) ? ' ' . trim(implode(' ', $options)) : '';
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->setDescription('Runs Codeception in containers.')
             ->addArgument('suite', InputArgument::OPTIONAL, 'The name of the suite to run.', 'unit')
             ->addOption(
                 'container-name',
                 null,
                 InputOption::VALUE_OPTIONAL,
                 'The name of the container that should be used to run the tests in the docker-compose stack.',
                 'wpbrowser'
             );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $process = $this->getProcess($input, $output);
        $process->run([ $this, 'handleOutput' ]);
    }
}
