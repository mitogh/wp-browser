<?php

namespace Codeception\Module;

use Codeception\Exception\ModuleConfigException;
use Codeception\Exception\ModuleException;
use Codeception\Lib\ModuleContainer;
use Codeception\Module;
use tad\WPBrowser\Adapters\Process;
use tad\WPBrowser\Exceptions\WpCliException;
use tad\WPBrowser\Traits\WithWpCli;
use function tad\WPBrowser\buildCommandline;

/**
 * Class WPCLI
 *
 * Wraps calls to the wp-cli tool.
 *
 * @package Codeception\Module
 */
class WPCLI extends Module
{
    use WithWpCli;

    const DEFAULT_TIMEOUT = 60;

    /**
     * An array of keys that will not be passed from the configuration to the wp-cli command.
     *
     * @var array
     */
    protected static $blockedKeys = [
        'throw' => true,
        'timeout' => true,
        'debug' => true,
        'color' => true,
        'prompt' => true,
        'quiet' => true,
        'env' => [
            'strict-args' => false
        ]
    ];

    /**
     * @param string $path The absolute path to the target WordPress installation root folder.
     *                     }
     *
     * @var array {
     */
    protected $requiredFields = ['path'];
    /**
     * @var string
     */
    protected $prettyName = 'WPCLI';
    /**
     * @var string
     */
    protected $bootPath;
    /**
     * @var array
     */
    protected $options = ['ssh', 'http', 'url', 'user', 'skip-plugins', 'skip-themes', 'skip-packages', 'require'];
    /**
     * An array of configuration variables and their default values.
     *
     * @var array
     */
    protected $config = [
        'throw' => true,
        'timeout' => 60,
        'allowRoot' => true,
    ];
    /**
     * The process timeout.
     *
     * @var int|float|null
     */
    protected $timeout;

    /**
     * WPCLI constructor.
     *
     * @param ModuleContainer $moduleContainer The module container containing this module.
     * @param array|null      $config          The module configuration.
     * @param Process|null    $process         The process adapter.
     */
    public function __construct(ModuleContainer $moduleContainer, $config = null, Process $process = null)
    {
        parent::__construct($moduleContainer, $config);
        $this->wpCliProcess = $process ?: new Process();
    }

    /**
     * Executes a wp-cli command targeting the test WordPress installation.
     *
     * @example
     * ```php
     * // Activate a plugin via wp-cli in the test WordPress site.
     * $I->cli('plugin activate my-plugin');
     * // Change a user password.
     * $I->cli('user update luca --user_pass=newpassword');
     * ```
     *
     * @param string|array $userCommand The string of command and parameters as it would be passed to wp-cli minus `wp`.
     *
     * @return int The command exit value; `0` usually means success.
     *
     *
     * @throws ModuleException If the status evaluates to non-zero and the `throw` configuration
     *                                                parameter is set to `true`.
     * @throws ModuleConfigException If a required wp-cli file cannot be found or the WordPress path does not exist
     *                               at runtime.
     */
    public function cli($userCommand = 'core version')
    {
        $return = $this->run($userCommand);

        return $return[1];
    }

    /**
     * {@inheritDoc}
     */
    protected function debugSection($title, $message)
    {
        parent::debugSection($this->prettyName . ' ' . $title, $message);
    }

    /**
     * Returns an associative array of wp-cli options parsed from the config array.
     *
     * Users can set additional options that will be passed to the wp-cli command; here is where they are parsed.
     *
     * @param null|string|array $userCommand The user command to parse for inline options.
     *
     * @return array An associative array of options, parsed from the current config.
     */
    protected function getConfigOptions($userCommand = null)
    {
        $inlineOptions = $this->parseWpCliInlineOptions((array)$userCommand);
        $configOptions = array_diff_key($this->config, static::$blockedKeys, $inlineOptions);
        unset($configOptions['path']);

        if (empty($configOptions)) {
            return [];
        }

        return $this->wpCliOptions($configOptions);
    }

    /**
     * Evaluates the exit status of the command.
     *
     * @param string $output The process output.
     * @param int          $status The process status code.
     *
     * @throws ModuleException If the exit status is lt 0 and the module configuration is set to throw.
     */
    protected function evaluateStatus($output, $status)
    {
        if ((int)$status !== 0 && !empty($this->config['throw'])) {
            $message = "wp-cli terminated with status [{$status}] and output [{$output}]\n\nWPCLI module is configured "
                . 'to throw an exception when wp-cli terminates with an error status; '
                . 'set the `throw` parameter to `false` to avoid this.';

            throw new ModuleException(__CLASS__, $message);
        }
    }

    /**
     * Returns the output of a wp-cli command as an array optionally allowing a callback to process the output.
     *
     * @param string|array $userCommand The command to execute, minus the `wp` part, as a string or as an array in the
     *                                  format `['plugin', 'list', '--field=name']`.
     * @param callable     $splitCallback An optional callback function in charge of splitting the results array.
     *
     * @return array An array containing the output of wp-cli split into single elements.
     *
     * @throws \Codeception\Exception\ModuleException If the $splitCallback function does not return an array.
     * @throws ModuleConfigException If the path to the WordPress installation does not exist.
     *
     * @example
     * ```php
     * // Return a list of inactive themes, like ['twentyfourteen', 'twentyfifteen'].
     * $inactiveThemes = $I->cliToArray('theme list --status=inactive --field=name');
     * // Get the list of installed plugins and only keep the ones starting with "foo".
     * $fooPlugins = $I->cliToArray(['plugin', 'list', '--field=name'], function($output){
     *      return array_filter(explode(PHP_EOL, $output), function($name){
     *              return strpos(trim($name), 'foo') === 0;
     *      });
     * });
     * ```
     *
     */
    public function cliToArray($userCommand = 'post list --format=ids', callable $splitCallback = null)
    {
        $output = $this->cliToString($userCommand);

        if (empty($output)) {
            return [];
        }

        $hasSplitCallback = null !== $splitCallback && is_callable($splitCallback);
        $originalOutput = $output;

        if (is_callable($splitCallback)) {
            $output = $splitCallback($output, $userCommand, $this);
        } else {
            $output = !preg_match('/[\\n]+/', $output) ?
                preg_split('/\\s+/', $output)
                : preg_split('/\\s*\\n+\\s*/', $output);
        }

        if (!is_array($output) && $hasSplitCallback) {
            throw new ModuleException(
                __CLASS__,
                "Split callback must return an array, it returned: \n" . print_r(
                    $output,
                    true
                ) . "\nfor original output:\n" . print_r(
                    $originalOutput,
                    true
                )
            );
        }

        return empty($output) ? [] : array_map('trim', $output);
    }

    /**
     * {@inheritDoc}
     */
    protected function validateConfig()
    {
        parent::validateConfig();
        $this->validateTimeout();
    }

    /**
     * Validates the configuration path to make sure it's a directory.
     *
     * @throws ModuleConfigException If the configuration path is not a directory.
     */
    protected function validatePath()
    {
        if (!is_dir($this->config['path'])) {
            throw new ModuleConfigException(
                __CLASS__,
                'Specified path [' . $this->config['path'] . '] is not a directory.'
            );
        }

        $this->wpCliWpRootDir = realpath($this->config['path']) ?: $this->config['path'];
    }

    /**
     * Validates the configuration timeout.
     *
     * @throws ModuleConfigException If the configuration timeout is not valid.
     */
    protected function validateTimeout()
    {
        $timeout = static::DEFAULT_TIMEOUT;

        if (array_key_exists('timeout', $this->config)) {
            $timeout = empty($this->config['timeout']) ? null : $this->config['timeout'];
        }

        if (!($timeout === null || is_numeric($timeout))) {
            throw new ModuleConfigException($this, "Timeout [{$this->config['timeout']}] is not valid.");
        }

        $this->timeout = is_string($timeout) ? (float)$timeout : $timeout;
    }

    /**
     * Returns the output of a wp-cli command as a string.
     *
     * @param string|array $userCommand The command to execute, minus the `wp` part, as a string or as an array in the
     *                                  format `['option','get','admin_email']`.
     *
     * @return string The command output, if any.
     *
     * @throws ModuleConfigException If the path to the WordPress installation does not exist.
     * @throws ModuleException If there's an exception while running the command and the module is configured to throw.
     *
     * @example
     * ```php
     * // Return the current site administrator email, using string command format.
     * $adminEmail = $I->cliToString('option get admin_email');
     * // Get the list of active plugins in JSON format.
     * $activePlugins = $I->cliToString(['wp','option','get','active_plugins','--format=json']);
     * ```
     */
    public function cliToString($userCommand)
    {
        $return = $this->run($userCommand);

        return $return[0];
    }

    /**
     * Builds the process environment from the configuration options.
     *
     * @return array An associative array of environment.
     */
    protected function buildProcessEnv()
    {
        return array_filter([
            'WP_CLI_CACHE_DIR' => isset($this->config['env']['cache-dir']) ? $this->config['env']['cache-dir'] : false,
            'WP_CLI_CONFIG_PATH' => isset($this->config['env']['config-path']) ?
                $this->config['env']['config-path']
                : false,
            'WP_CLI_CUSTOM_SHELL' => isset($this->config['env']['custom-shell'])
                ?$this->config['env']['custom-shell']
                : false,
            'WP_CLI_DISABLE_AUTO_CHECK_UPDATE' => empty($this->config['env']['disable-auto-check-update']) ? '0' : '1',
            'WP_CLI_PACKAGES_DIR' => isset($this->config['env']['packages-dir']) ?
                $this->config['env']['packages-dir']
                : false,
            'WP_CLI_PHP' => isset($this->config['env']['php']) ? $this->config['env']['php'] : false,
            'WP_CLI_PHP_ARGS' => isset($this->config['env']['php-args']) ? $this->config['env']['php-args'] : false,
            'WP_CLI_STRICT_ARGS_MODE' => !empty($this->config['env']['strict-args']) ? '1' : false,
        ]);
    }

    /**
     * Runs a wp-cli command and returns its output and status.
     *
     * @param string|array $userCommand The user command, in the format supported by the Symfony Process class.
     *
     * @return array The command process output and status.
     *
     * @throws ModuleConfigException If the wp-cli path is wrong.
     * @throws ModuleException If there's an issue while running the command.
     */
    protected function run($userCommand)
    {
        $this->validatePath();

        $userCommand = buildCommandline($userCommand);

        /**
         * Set an environment variable to let client code know the request is coming from the host machine.
         * Set the value to a string to make it so that Symfony\Process will pick it up while populating the env.
         */
        putenv('WPBROWSER_HOST_REQUEST="1"');
        $_ENV['WPBROWSER_HOST_REQUEST'] = '1';

        $this->debugSection('command', $userCommand);

        $command = array_merge($this->getConfigOptions($userCommand), (array) $userCommand);
        $env = $this->buildProcessEnv();

        $this->debugSection('command with configuration options', $command);
        $this->debugSection('command with environment', $env);

        try {
            $process = $this->executeWpCliCommand($command, $this->timeout, $env);
        } catch (WpCliException $e) {
            if (!empty($this->config['throw'])) {
                throw new ModuleException($this, $e->getMessage());
            }

            $this->debugSection('command exception', $e->getMessage());

            return ['',1];
        }

        if (!empty($this->config['throw']) && $process->getErrorOutput()) {
            throw new ModuleException($this, $process->getErrorOutput());
        }

        $output = $process->getErrorOutput() ?: $process->getOutput();
        $status = $process->getExitCode();

        // If the process returns `null`, then it's not terminated.
        if ($status === null) {
            throw new ModuleException(
                $this,
                'Command process did not terminate; commandline: ' . $process->getCommandLine()
            );
        }

        $this->debugSection('output', $output);
        $this->debugSection(' status', $status);

        $this->evaluateStatus($output, $status);

        return [$output, $status];
    }
}
