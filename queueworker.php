#!/usr/bin/php
<?php
//only available via command line (this file shold be outside the web folder anyway)
if (empty($_SERVER['argc'])) {
    die();
}

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
require_once($loggerFile);
require_once($connectionFile);
require_once($workerFile);

$logger = new $loggerClass($config['logger']);
$connection = new $connectionClass($config['connection']);
$worker = new $workerClass($connection, $config['tasks']);
$worker->setLogger($logger);

//log a message after proceeding X tasks without pause
$noticeAfterTasksCount = 100; //notice after the 100th, 200th, 300th, ... Task without break

$start = microtime(true);

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

    //stop processing if no queued task was found
    if ($status === false) {
        break;
    }

    $processed++;

    if ($noticeAfterTasksCount && !($processed % $noticeAfterTasksCount)) {
        $end = microtime(true);
        $logger->logNotice('Worker: running, processed '.$processed.' tasks ('.$errors.' errors). took '.(number_format($end-$start, 4,'.','')).'s so far...');
    }
} while (true);
if ($processed) {
    $end = microtime(true);
    $logger->logNotice('Worker: finished, processed '.$processed.' tasks ('.$errors.' errors). took '.(number_format($end-$start, 4,'.','')).'s - ready for new tasks');
}
