<?php
/**
 * An open, non final, version of SebastianBergmann\Environment\OperatingSystem class.
 *
 * @package tad\WPBrowser\Environment
 */


namespace tad\WPBrowser\Environment;

/**
 * Class OperatingSystem
 *
 * @package tad\WPBrowser\Environment
 */
class OperatingSystem
{
    const LINUX = 'Linux'   ;
    const WINDOWS = 'Windows'   ;
    const MAC = 'Darwin'    ;
    const BSD = 'BSD';
    const SOLARIS = 'Solaris';
    const UNKNOWN = 'Unknown';

    /**
     * Returns PHP_OS_FAMILY (if defined (which it is on PHP >= 7.2)).
     * Returns a string (compatible with PHP_OS_FAMILY) derived from PHP_OS otherwise.
     */
    public function getFamily()
    {
        if (\defined('PHP_OS_FAMILY')) {
            return \PHP_OS_FAMILY;
        }

        if (\DIRECTORY_SEPARATOR === '\\') {
            return static::WINDOWS;
        }

        switch (\PHP_OS) {
            case 'Darwin':
                return static::MAC;

            case 'DragonFly':
            case 'FreeBSD':
            case 'NetBSD':
            case 'OpenBSD':
                return static::BSD;

            case 'Linux':
                return static::LINUX;

            case 'SunOS':
                return static::SOLARIS;

            default:
                return static::UNKNOWN;
        }
    }
}
