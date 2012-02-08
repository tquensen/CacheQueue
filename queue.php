#!/usr/bin/php
<?php
//only available via command line (this file shold be outside the web folder anyway)
if (empty($_SERVER['argc'])) {
    die();
}

//add CacheQueue parent folder to include path
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__).'/lib');

//define config file
$configFile = dirname(__FILE__).'/config.php';

$config = array();
require_once($configFile);

//initialize factory
require_once('CacheQueue/Factory/FactoryInterface.php');
require_once('CacheQueue/Factory/Factory.php');

$factory = new \CacheQueue\Factory\Factory($config);

$logger = $factory->getLogger();
$connection = $factory->getConnection();
$connection = $factory->getConnection();

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