#!/usr/bin/php
<?php
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__).'/lib');

$configFile = dirname(__FILE__).'/config.php';

$config = array();
require_once($configFile);

$connectionClass = $config['classes']['connection'];
$loggerClass = $config['classes']['logger'];
$connectionFile = str_replace('\\', DIRECTORY_SEPARATOR, trim($connectionClass, '\\')).'.php';
$loggerFile = str_replace('\\', DIRECTORY_SEPARATOR, trim($loggerClass, '\\')).'.php';

require_once('CacheQueue/ILogger.php');
require_once('CacheQueue/IConnection.php');
require_once($loggerFile);
require_once($connectionFile);

$logger = new $loggerClass($config['logger']);
$connection = new $connectionClass($config['connection']);

if (!isset($argv[1])) {
    $logger->logError('Cleanup: parameter required!');
}

switch (strtolower($argv[1])) {
    case 'all':
        $status = $connection->clear(0, true);
        $logger->logNotice('Cleanup ALL: '.($status ? 'success' : 'error'));
        break;
    case 'persistent':
        $status = $connection->removePersistent();
        $logger->logNotice('Cleanup PERSISTENT: '.($status ? 'success' : 'error'));
        break;
    case 'key':
        $key = !empty($argv[2]) ? $argv[2] : false;
        $force = !empty($argv[3]);
        if (!$key) {
            $logger->logError('Cleanup KEY: key required!');
        }
        $status = $connection->remove($key, $force);
        $logger->logNotice('Cleanup KEY '.$key.($force ? ' (force)' : '').': '.($status ? 'success' : 'error'));
        break;
     case 'outdated':
        $outdatedFor = !empty($argv[2]) ? (int) $argv[2] : 0;
        $status = $connection->clear($outdatedFor, false);
        $logger->logNotice('Cleanup OUTDATED (for '.$outdatedFor.'s): '.($status ? 'success' : 'error'));
        break;
}