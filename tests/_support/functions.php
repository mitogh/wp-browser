<?php

namespace tad\WPBrowser\Tests\Support;

function importDump($dumpFile, $dbName, $dbUser = 'root', $dbPass = 'root', $dbHost = 'localhost')
{
    if (strpos($dbHost, ':') >0) {
        list($dbHost, $dbPort) = explode(':', $dbHost);
        $dbHost = sprintf('%s -P %d', $dbHost, $dbPort);
    }

    $commandTemplate = 'mysql -h %s -u %s %s %s < %s';
    $dbPassEntry = $dbPass ? '-p' . $dbPass : '';

    if (version_compare(getMySQLVersion(), '5.5.3', '<')) {
        $sql = file_get_contents($dumpFile);
        if (false === $sql) {
            return false;
        }

        $conversionMarker = "#converted";
        if (false === strpos($sql, $conversionMarker)) {
            $sql = "{$conversionMarker}\n" . $sql;
            $sql = preg_replace('(CHARSET=utf8[^\\s]*)', 'CHARSET=utf8', $sql);
            $sql = preg_replace('(COLLATE=utf8[^\\s]*)', 'COLLATE=utf8_bin', $sql);
            if (false === file_put_contents($dumpFile, $sql)) {
                return false;
            }
        }
    }

    $command = sprintf($commandTemplate, $dbHost, $dbUser, $dbPassEntry, $dbName, $dumpFile);
    exec($command, $output, $status);

    return $status !== false;
}

function getMySQLVersion()
{
    $output = shell_exec('mysql -V');
    preg_match('@\d+\.\d+\.\d+@', $output, $version);
    return $version[0];
}

/**
* Normalizes a string new line bytecode for comparison
* through Unix and Windows environments.
*
* @see https://stackoverflow.com/a/7836692/2056484
*/
function normalizeNewLine($str)
{
    return preg_replace('~(*BSR_ANYCRLF)\R~', "\n", $str);
}
