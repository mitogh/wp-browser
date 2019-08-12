<?php
/**
 * The API implemented by most wp-browser commands.
 *
 * @package tad\WPBrowser\Command
 */

namespace tad\WPBrowser\Command;

use Codeception\CustomCommandInterface as CodeceptionCustomCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Class CustomCommandInterface
 *
 * @package tad\WPBrowser\Command
 */
interface CustomCommandInterface extends CodeceptionCustomCommandInterface
{
    /**
     * Returns the command executable line in the array format requested by the Symfony Process component.
     *
     * @param InputInterface       $input The current command input.
     * @param OutputInterface|null $output
     *
     * @return string The command executable line in the array format requested by the Symfony Process component.
     */
    public function getCommandLine(InputInterface $input, OutputInterface $output = null);

    /**
     * Returns the command Symfony Process, ready to run.
     *
     * @param InputInterface       $input  The current input.
     * @param OutputInterface|null $output The current output instance, if any.
     *
     * @return Process The command Symfony process, ready to run.
     */
    public function getProcess(InputInterface $input, OutputInterface $output = null);

    /**
     * Returns the command output, if any.
     *
     * @param InputInterface       $input  The current input.
     * @param OutputInterface|null $output The current output instance, if any.
     *
     * @return string The command output, if any.
     */
    public function getOutput(InputInterface $input, OutputInterface $output = null);
}
