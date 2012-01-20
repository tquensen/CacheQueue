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
$worker = $factory->getWorker();

//log a message after proceeding X tasks without pause
$noticeAfterTasksCount = 100; //notice after the 100th, 200th, 300th, ... Task without break

try {
    $start = microtime(true);
    $processed = 0;
    $errors = 0;
    do {
        $status = null;
        try {
            if ($job = $worker->getJob()) {
                $worker->work($job); 
            } else {
                //pause processing for 1 sec if no queued task was found
                break;
            }
        } catch (\CacheQueue\Exception\Exception $e) {
            //log CacheQueue exceptions 
            $errors++;
            $logger->logError('Worker: error '.(string) $e);
        }

        $processed++;

        if ($noticeAfterTasksCount && !($processed % $noticeAfterTasksCount)) {
            $end = microtime(true);
            $count = $connection->getQueueCount();
            $logger->logNotice('Worker: running, processed '.$processed.' tasks ('.$errors.' errors), '.(int)$count.' tasks remaining. took '.(number_format($end-$start, 4,'.','')).'s so far...');
        }
    } while (true);
    if ($processed) {
        $end = microtime(true);
        $logger->logNotice('Worker: finished, processed '.$processed.' tasks ('.$errors.' errors). took '.(number_format($end-$start, 4,'.','')).'s - ready for new tasks');
    }
} catch (Exception $e) {
    //handle exceptions
    $logger->logError('Worker: Exception '.(string) $e);
    exit;
}