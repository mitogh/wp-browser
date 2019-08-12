<?php
/**
 * Builds and returns CLI commands implemented by the wp-browser package.
 *
 * @package tad\WPBrowser\Command
 */


namespace tad\WPBrowser\Command;

use Codeception\CustomCommandInterface;

/**
 * Class CommandFactory
 *
 * @package tad\WPBrowser\Command
 */
class CommandFactory
{
    /**
     * Returns an instance of a custom command.
     *
     * @param string $commandClass The command class fully-qualified name.
     * @param string|null $name The command name.
     *
     * @return \tad\WPBrowser\Command\CustomCommandInterface The built command instance.
     */
    public function buildCustomCommand($commandClass, $name = null)
    {
        return new $commandClass($name);
    }
}
