<?php
/**
 * Wraps the Symfony process class to provide an injectable instance.
 *
 * @package tad\WPBrowser\Adapters
 */

namespace tad\WPBrowser\Adapters;

/**
 * Class Process
 *
 * @package tad\WPBrowser\Adapters
 */
class Process
{
    /**
     * Builds a Symfony process for a command.
     *
     * @param array          $command The components of the command to run.
     * @param string|null    $cwd     The current working directory to set for the process, if any.
     * @param array|null     $env     The environment variables or null to use the same environment as the current PHP
     *                                process.
     * @param mixed|null     $input   The input as stream resource, scalar or \Traversable, or null for no input.
     * @param int|float|null $timeout The timeout in seconds or null to disable.
     *
     * @return \Symfony\Component\Process\Process The built, and ready to run, process handler.
     */
    public function forCommand(array $command, $cwd = null, array $env = null, $input = null, $timeout = null)
    {
        return new \Symfony\Component\Process\Process((array) $command, $cwd, $env, $input, $timeout);
    }
}
