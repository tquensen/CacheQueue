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

$status = $connection->cleanup(time() - $config['cleanupTime']);

$logger->logNotice('Cleanup: '.($status ? 'success' : 'error'));