<?php
/**
 * An exception thrown during the execution of a wp-browser console command.
 *
 * @package Codeception\Command
 */


namespace Codeception\Command;

use Symfony\Component\Console\Command\Command;

/**
 * Class Exception
 *
 * @package Codeception\Command
 */
class Exception extends \RuntimeException
{
    public static function becauseThereWasAnError(Command $command, $context, $error)
    {
        $message = sprintf(
            'wp-browser console command (%s) error %s: %s',
            get_class($command),
            $context,
            $error
        );

        return new static($message);
    }
}
