<?php namespace Codeception\Command;

use Codeception\Test\Unit;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Process as SymfonyProcess;
use tad\WPBrowser\Command\CommandSupportInterface;
use tad\WPBrowser\Environment\OperatingSystem;

class ContainerRunTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * It should be instantiable
     *
     * @test
     */
    public function should_be_instantiable()
    {
        $this->assertInstanceOf(ContainerRun::class, new ContainerRun());
    }

    public function osAndExpectedShellCommandsDataSet()
    {
        return [
            'macos'   => [
                OperatingSystem::MAC,
                'docker-compose run --rm -e XDEBUG_REMOTE_HOST=host.docker.internal wpbrowser run unit'
            ],
            'linux'   => [
                OperatingSystem::LINUX,
                'docker-compose run --rm -e XDEBUG_REMOTE_HOST=172.17.0.1 wpbrowser run unit'
            ],
            'windows' => [
                OperatingSystem::WINDOWS,
                'docker-compose run --rm -e XDEBUG_REMOTE_HOST=host.docker.internal wpbrowser run unit'
            ],
        ];
    }

    /**
     * It should correctly use the current OS information
     *
     * @test
     * @dataProvider osAndExpectedShellCommandsDataSet
     */
    public function should_correctly_use_the_current_os_information($osFamily, $expectedShellCommand)
    {
        $commandSupport = $this->makeEmpty(
            CommandSupportInterface::class,
            [
                'getOperatingSystemFamily' => $osFamily,
                'getCommandOutput'         => function ($class) use ($osFamily) {
                    $this->assertEquals(ContainerHostAddress::class, $class);

                    return $osFamily === OperatingSystem::LINUX ?
                        '172.17.0.1'
                        : 'host.docker.internal';
                }
            ]
        );
        $input          = $this->makeEmpty(InputInterface::class);

        $command = new ContainerRun('test', $commandSupport);

        $this->assertEquals($expectedShellCommand, implode(' ', $command->getCommandLine($input)));
    }

    /**
     * It should allow specifying the container name
     *
     * @test
     */
    public function should_allow_specifying_the_container_name()
    {
        $commandSupport = $this->makeEmpty(
            CommandSupportInterface::class,
            [
                'getOperatingSystemFamily' => OperatingSystem::MAC,
                'getCommandOutput'         => 'host.docker.internal'
            ]
        );
        $input          = $this->makeEmpty(
            InputInterface::class,
            [
                'hasOption' => static function ($name) {
                    return $name === 'container-name';
                },
                'getOption' => function ($name) {
                    if ($name !== 'container-name') {
                        $this->fail('Only the container-name input option should be accessed.');
                    }

                    return 'test-container';
                },
            ]
        );

        $command = new ContainerRun('test', $commandSupport);

        $this->assertEquals(
            'docker-compose run --rm -e XDEBUG_REMOTE_HOST=host.docker.internal test-container run unit',
            implode(' ', $command->getCommandLine($input))
        );
    }

    /**
     * It should allow specifying the command arguments for run
     *
     * @test
     */
    public function should_allow_specifying_the_command_arguments_for_run()
    {
        $commandSupport = $this->makeEmpty(
            CommandSupportInterface::class,
            [
                'getOperatingSystemFamily' => OperatingSystem::MAC,
                'getCommandOutput'         => 'host.docker.internal'
            ]
        );
        $input          = $this->makeEmpty(
            InputInterface::class,
            [
                'hasArgument' => static function ($name) {
                    if ($name !== 'suite') {
                        $this->fail('Only the suite argument should be accessed.');
                    }

                    return true;
                },
                'getArgument' => function ($name) {
                    if ($name !== 'suite') {
                        $this->fail('Only the suite argument should be accessed.');
                    }

                    return 'some_suite';
                },
                'hasOption'   => static function ($name) {
                    return false;
                }
            ]
        );

        $command = new ContainerRun('test', $commandSupport);

        $shellCommand = $command->getCommandLine($input);
        $this->assertEquals(
            'docker-compose run --rm -e XDEBUG_REMOTE_HOST=host.docker.internal wpbrowser run some_suite',
            implode(' ', $shellCommand)
        );
    }

    /**
     * It should run the process correctly
     *
     * @test
     */
    public function should_run_the_process_correctly()
    {
        $expectedCommand = 'docker-compose run --rm -e XDEBUG_REMOTE_HOST=host.docker.internal test-runner run test_suite';
        list( $commandSupport, $input, $output ) = $this->setupCommandDependenciesToExpect(
            $expectedCommand,
            [
                'suite' => 'test_suite'
            ],
            [
                'container-name' => 'test-runner'
            ]
        );

        $command = new ContainerRun('test', $commandSupport);

        $command->run($input, $output);
    }

    protected function setupCommandDependenciesToExpect(
        $expectedCommand,
        array $expectedArguments = [],
        array $expectedOptions = [],
        array $outputMethods = []
    ) {
        $commandSupport = $this->makeEmpty(
            CommandSupportInterface::class,
            [
                'getOperatingSystemFamily' => OperatingSystem::MAC,
                'getCommandOutput'         => 'host.docker.internal',
                'getProcessForCommand'     => function ($command) use ($expectedCommand) {
                    $this->assertEquals($expectedCommand, implode(' ', $command));

                    return $this->makeEmpty(Process::class);
                }
            ]
        );
        $input          = $this->makeEmpty(
            InputInterface::class,
            [
                'hasArgument' => function ($name) use ($expectedArguments) {
                    return isset($expectedArguments[ $name ]);
                },
                'getArgument' => function ($name) use ($expectedArguments) {
                    if (isset($expectedArguments[ $name ])) {
                        return $expectedArguments[ $name ];
                    }
                    $this->fail("Unexpected getArgument call for {$name}.");
                },
                'hasOption'   => function ($name) use ($expectedOptions) {
                    return isset($expectedOptions[ $name ]);
                },
                'getOption'   => function ($name) use ($expectedOptions) {
                    if (isset($expectedOptions[ $name ])) {
                        return $expectedOptions[ $name ];
                    }
                    $this->fail("Unexpected getOption call for {$name}.");
                }
            ]
        );
        $output         = $this->makeEmpty(OutputInterface::class, $outputMethods);

        return [ $commandSupport, $input, $output ];
    }

    /**
     * It should correctly echo the command output during execution
     *
     * @test
     */
    public function should_correctly_echo_the_command_output_during_execution()
    {
        $command = new ContainerRun();


        $this->expectOutputString(
            'All good.',
            $command->handleOutput(SymfonyProcess::OUT, 'All good.')
        );
    }

    /**
     * It should correctly output errors
     *
     * @test
     */
    public function should_correctly_output_errors()
    {
        $command = new ContainerRun();

        $this->expectOutputString(
            'ERR > An error happened!',
            $command->handleOutput(SymfonyProcess::ERR, 'An error happened!')
        );
    }

    public function verbosityLevelsAndCounts()
    {
        return [
            'normal'  => [ OutputInterface::VERBOSITY_NORMAL, '' ],
            '-vv'     => [ OutputInterface::VERBOSITY_VERBOSE, ' -vv' ],
            '-vvv'    => [ OutputInterface::VERBOSITY_VERY_VERBOSE, ' -vvv' ],
            '-q'      => [ OutputInterface::VERBOSITY_QUIET, ' -q' ],
            '--debug' => [ OutputInterface::VERBOSITY_DEBUG, ' --debug' ],
        ];
    }

    /**
     * It should pass verbosity down to codecept command
     *
     * @test
     * @dataProvider verbosityLevelsAndCounts
     */
    public function should_pass_verbosity_down_to_codecept_command($verbosityLevel, $expectedVerbosityOption)
    {
        $expectedCommand = 'docker-compose run --rm -e XDEBUG_REMOTE_HOST=host.docker.internal wpbrowser run unit'
                           . $expectedVerbosityOption;
        list( $commandSupport, $input, $output ) = $this->setupCommandDependenciesToExpect(
            $expectedCommand,
            [],
            [],
            [ 'getVerbosity' => $verbosityLevel ]
        );

        $command = new ContainerRun('test', $commandSupport);
        $command->run($input, $output);
    }

    /**
     * It should run with correct defaults
     *
     * @test
     */
    public function should_run_with_correct_defaults()
    {
        $expectedCommand = 'docker-compose run --rm -e XDEBUG_REMOTE_HOST=host.docker.internal wpbrowser run unit';
        list( $commandSupport, $input, $output ) = $this->setupCommandDependenciesToExpect(
            $expectedCommand,
            [
                'suite' => 'unit'
            ]
        );

        $command = new ContainerRun('test', $commandSupport);
        $command->run($input, $output);
    }
}
