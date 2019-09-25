<?php
/**
 * A module to deal with container context situations that might affect the tests.
 *
 * @since   TBD
 *
 * @package Codeception\Module
 */

namespace Codeception\Module;

use Codeception\Exception\ModuleException;
use Codeception\Module;

/**
 * Class ContainerContext
 *
 * @since   TBD
 *
 * @package Codeception\Module
 */
class ContainerContext extends Module
{
    protected $config = [
        'flagEnvVar' => 'CONTAINER',
        'timeout' => 3,
        'interval' => 1
    ];

    public function waitForFile($file, callable $else = null)
    {
        $onFailure = $else ? $else : function () use ($file) {
            throw new ModuleException($this, sprintf('File %s not found.', $file));
        };

        if (!$this->isContainerContext()) {
            $this->debug('Not in container context.');
            return file_exists($file) || $onFailure();
        }

        $timeout = isset($this->config['timeout']) ? $this->config['timeout'] : 3;
        $interval = isset($this->config['interval']) ? $this->config['interval'] : 1;
        $limit = time() + $timeout;

        while (time() < $limit) {
            if (file_exists($file)) {
                return true;
            }

            $this->debug(sprintf('Sleeping for %ds waiting for file %s.', $interval, $file));
            sleep($interval);
        }

        return file_exists($file) || $onFailure();
    }

    public function isContainerContext()
    {
        $flag = $this->config['flagEnvVar'];

        $flags = is_array($this->config['flagEnvVar']) ?
            $this->config['flagEnvVar']
            : explode(',', $flag);

        foreach ($flags as $flag) {
            if (getenv($flag)) {
                return true;
            }
        }
        return false;
    }
}
