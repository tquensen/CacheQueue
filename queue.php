#!/usr/bin/php
<?php
//only available via command line (this file shold be outside the web folder anyway)
if (empty($_SERVER['argc'])) {
    die();
}

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__).'/lib');

$configFile = dirname(__FILE__).'/config.php';
$workerFile = dirname(__FILE__).'/queueworker.php';

$config = array();
require_once($configFile);

$connectionClass = $config['classes']['connection'];
$loggerClass = $config['classes']['logger'];

//method 1) load autoloader and register CacheQueue classes
require_once('SplClassLoader/SplClassLoader.php');
$classLoader = new SplClassLoader('CacheQueue');
$classLoader->register();   

//method 2) load required classes - uncomment if you don't use an autoloader 
/*
$connectionFile = str_replace('\\', DIRECTORY_SEPARATOR, trim($connectionClass, '\\')).'.php';
$loggerFile = str_replace('\\', DIRECTORY_SEPARATOR, trim($loggerClass, '\\')).'.php';

require_once('CacheQueue/Exception/Exception.php');
require_once('CacheQueue/Logger/LoggerInterface.php');
require_once('CacheQueue/Connection/ConnectionInterface.php');
require_once($loggerFile);
require_once($connectionFile);
*/


$logger = new $loggerClass($config['logger']);
$connection = new $connectionClass($config['connection']);

$tasksPerWorker = 10;

do {
    try {
        if ($count = $connection->getQueueCount()) {
            $workerCount = ceil($count / $tasksPerWorker);
            $logger->logNotice('Queue: found '.$count.' new tasks, starting '.$workerCount.' worker.');
            for ($i=0; $i<$workerCount; $i++) {
                exec($workerFile);
            }
        } 
//        else {
//            $logger->logNotice('Queue: no new tasks.');
//        }
    } catch (\CacheQueue\Exception\Exception $e) {
        //log CacheQueue exceptions 
        $logger->logError('Queue: error '.(string)$e);
    } catch (Exception $e) {
        //handle exceptions
        $logger->logError('Worker: Exception '.(string) $e);
        exit;
    }
    sleep(1);
} while(true);