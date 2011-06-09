#!/usr/bin/php
<?php
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__).'/lib');

$configFile = dirname(__FILE__).'/config.php';

$config = array();
require_once($configFile);

$connectionClass = $config['classes']['connection'];
$loggerClass = $config['classes']['logger'];
$workerClass = $config['classes']['worker'];
$connectionFile = str_replace('\\', DIRECTORY_SEPARATOR, trim($connectionClass, '\\')).'.php';
$loggerFile = str_replace('\\', DIRECTORY_SEPARATOR, trim($loggerClass, '\\')).'.php';
$workerFile = str_replace('\\', DIRECTORY_SEPARATOR, trim($workerClass, '\\')).'.php';

require_once('CacheQueue/ILogger.php');
require_once('CacheQueue/IConnection.php');
require_once('CacheQueue/IWorker.php');
require_once('CacheQueue/ITask.php');
require_once($loggerFile);
require_once($connectionFile);
require_once($workerFile);

$logger = new $loggerClass($config['logger']);
$connection = new $connectionClass($config['connection']);
$worker = new $workerClass($connection, $config['tasks']);

do {
    
    $processed = 0;
    $errors = 0;
    do {
        $status = null;
        try {
            $status = $worker->work(); 
        } catch (Exception $e) {
            $errors++;
            $logger->logError('Worker: error '.(string) $e);
        }
    } while ($status !== false && ++$processed);
    if ($processed) {
        $logger->logNotice('Worker: finished with '.$processed.' Tasks ('.$errors.' errors).');
    }
    sleep(1); 
} while(true);


