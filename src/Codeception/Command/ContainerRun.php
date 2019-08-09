<?php
/**
 * Runs Codeception in containers.
 *
 * @package Codeception\Command
 */


namespace Codeception\Command;

use Codeception\CustomCommandInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process as SymfonyProcess;
use tad\WPBrowser\Adapters\Process;
use tad\WPBrowser\Environment\OperatingSystem;

/**
 * Class ContainerRun
 *
 * @package Codeception\Command
 */
class ContainerRun extends Command implements CustomCommandInterface
{
    /**
     * An instance of the operating system adapter.
     *
     * @var OperatingSystem
     */
    protected $operatingSystem;

    /**
     * An instance of the Symfony Process adapter.
     *
     * @var Process
     */
    protected $process;

    /**
     * ContainerRun constructor.
     *
     * @param string|null          $name            The name of the command; passing null means it must be set in
     *                                              configure().
     * @param OperatingSystem|null $operatingSystem The operating system abstraction adapter instance.
     * @param Process|null         $process         An instance of the Symfony Process adapter.
     */
    public function __construct($name = null, OperatingSystem $operatingSystem = null, Process $process = null)
    {
        parent::__construct($name);
        $this->operatingSystem = $operatingSystem ?: new OperatingSystem();
        $this->process         = $process ?: new Process();
    }

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
     * Returns the commmand line the command would run.
     *
     * @since TBD
     *
     * @param InputInterface       $input  The current input.
     * @param OutputInterface|null $output The current output.
     *
     * @return string The command line the the command would run.
     */
    public function getShellCommand(InputInterface $input, OutputInterface $output = null)
    {
        $preCommand            = '';
        $dockerComposeBin      = 'docker-compose';
        $dockerComposeCommand  = 'run';
        $dockerComposeOptions  = '--rm';
        $containerName         = $input->hasOption('container-name') ?
            $input->getOption('container-name')
            : 'wpbrowser';
        $suite                 = $input->hasArgument('suite') ? $input->getArgument('suite') : 'unit';
        $codeceptArgs          = "run {$suite}";
        $codeceptOutputOptions = $this->parseOutputOptions($output);

        $os = $this->operatingSystem->getFamily();

        if ($os === 'Linux') {
            $preCommand           = 'XDEBUG_REMOTE_HOST="$(ip -4 addr show docker0 | grep -Po \'inet \K[\d.]+\')"';
            $dockerComposeOptions .= ' -e XDEBUG_REMOTE_HOST="${XDEBUG_REMOTE_HOST}"';
        }

        return array_map(
            'trim',
            array_filter(
                [
                    $preCommand,
                    $dockerComposeBin,
                    $dockerComposeCommand,
                    $dockerComposeOptions,
                    $containerName,
                    $codeceptArgs,
                    $codeceptOutputOptions
                ]
            )
        );
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
        $command = $this->getShellCommand($input, $output);

        $env = [];
        if (isset($_SERVER['PATH'])) {
            $env['PATH'] = $_SERVER['PATH'];
        }

        $runnerProcess = $this->process->forCommand($command, codecept_root_dir(), $env);

        $runnerProcess->run([ $this, 'handleOutput' ]);
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

        return trim(implode(' ', $options));
    }
}
