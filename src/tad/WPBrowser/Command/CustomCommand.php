<?php
/**
 * Base implementation of container management commands.
 *
 * @package tad\WPBrowser\Command
 */


namespace tad\WPBrowser\Command;

use Symfony\Component\Console\Command\Command;

/**
 * Class CustomCommand
 *
 * @package tad\WPBrowser\Command
 */
abstract class CustomCommand extends Command implements CustomCommandInterface
{

    /**
     * An instance of the command support facade.
     *
     * @var CommandSupportInterface
     */
    protected $commandSupport;

    /**
     * ContainerHostAddress constructor.
     *
     * @param string|null             $name           The name of the command; passing null means it must be set in
     *                                                configure().
     * @param CommandSupportInterface $commandSupport An instance of the command support abstraction.
     */
    public function __construct($name = null, CommandSupportInterface $commandSupport = null)
    {
        parent::__construct($name);
        $this->commandSupport = $commandSupport ?: new CommandSupport();
    }
}
