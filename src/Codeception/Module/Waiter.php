<?php
/**
 * A module to deal with the zen art of waiting for something to happen.
 *
 * @package Codeception\Module
 */

namespace Codeception\Module;

use Codeception\Exception\ModuleException;
use Codeception\Module;

/**
 * Class Waiter
 *
 * @package Codeception\Module
 */
class Waiter extends Module

{
    /**
     * The default module configuration.
     *
     * @var array
     */
    protected $config = [
        'timeout' => 3,
        'interval' => .5
    ];

    /**
     * Waits for a file to exist.
     *
     * @example
     * ```php
     * // Wait for a plugin file to exist.
     * $file = '/var/www/html/wp-content/plugins/my-plugin/plugin.php';
     * $I->waitForFileToExist($file);
     * ```
     *
     * @param string $file The file to wait for.
     *
     * @return true If the expected file eventually came to be.
     */
    public function waitForFileToExist($file)
    {
        $onFailure = function () use ($file) {
            throw new ModuleException(
                $this,
                sprintf("Waited, but file %s was not found.", $file)
            );
        };
        $check = static function () use ($file) {
            return file_exists($file);
        };

        return $this->waitFor($check, $onFailure);
    }

    /**
     * Waits for a condition to come true, else it fails when the time runs out.
     *
     * @example
     * ```php
     * // Wait for a specific condition.
     * $optionExists = static function(){
     *         return (bool)get_option('some_option');
     * };
     * $else = static function(){
     *         throw new \RuntimeException('Option is empty after waiting.');
     * }
     * $I->waitFor($optionExists, $else);
     * ```
     *
     * @param callable $check The check function to run to know if the expected effect came to be or not. This wil be
     *                        evaluated as a boolean.
     * @param callable $onFailure What should be done when, and if, the check fails.
     *
     * @return true If the expected state ever came to be.
     */
    public function waitFor(callable $check, callable $onFailure)
    {
        list($timeout, $interval) = $this->getTimeoutAndInterval();

        $limit = time() + $timeout;

        while (time() < $limit) {
            if ($check()) {
                return true;
            }

            $this->debug(sprintf('Sleeping for %2.2fs...', $interval));
            usleep($interval * 1000000);
        }

        return $check() || $onFailure();
    }

    /**
     * Returns the timeout and interval values to use.
     *
     * @return array The timeout and interval values, in seconds.
     */
    protected function getTimeoutAndInterval()
    {
        $timeout = isset($this->config['timeout']) ? $this->config['timeout'] : 3;
        $interval = isset($this->config['interval']) ? $this->config['interval'] : .5;

        return array($timeout, $interval);
    }

    /**
     * Waits for a file to not exist.
     *
     * @param string $file The path to the file that should, eventually, not exist.
     *
     * @example
     * ```php
     * // Wait for a plugin file to be deleted.
     * $file = '/var/www/html/wp-content/plugins/my-plugin/plugin.php';
     * $I->waitForFileToNotExist($file);
     * ```
     *
     * @return true If the file does not exist.
     */
    public function waitForFileToNotExist($file)
    {
        $onFailure = function () use ($file) {
            throw new ModuleException(
                $this,
                sprintf("Waited , but file %s still exists.", $file)
            );
        };
        $check = static function () use ($file) {
            return !file_exists($file);
        };

        return $this->waitFor($check, $onFailure);
    }
}
