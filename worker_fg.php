#!/usr/bin/php
<?php
//only available via command line (this file shold be outside the web folder anyway)
if (empty($_SERVER['argc'])) {
    die();
}
    
//add CacheQueue parent folder to include path
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__).'/lib');

//enable PHP 5.3+ garbage collection
gc_enable();

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

$workerFile = __DIR__.'/worker_bg.php';

//log a "status" message only after X seconds
$noticeAfterMoreThanSeconds = 30;

$start = microtime(true);
$time = 0;
$processed = 0;
$errors = 0;

do {
    try {
        if ($connection->getQueueCount()) {
            try {
                $ts = microtime(true);
                $response = exec($workerFile);
                if ($response && strpos($response, '|')) {
                    list($newProcessed, $newErrors) = explode('|', $response);
                    $processed += (int) $newProcessed;
                    $errors += (int) $newErrors;
                }
                $time += microtime(true) - $ts;
            } catch (Exception $e) {
                $time += microtime(true) - $ts;
                throw $e;
            }
        } 
    } catch (\CacheQueue\Exception\Exception $e) {
        //log CacheQueue exceptions 
        $logger->logError('Queue: error '.(string)$e);
        unset ($e);
    } catch (Exception $e) {
        //handle exceptions
        $logger->logError('Queue: Exception '.(string) $e);
        exit;
    }
    
    $end = microtime(true);
    if ($processed && (!$noticeAfterMoreThanSeconds || ($end - $start > $noticeAfterMoreThanSeconds))) {
        $queueCount = $connection->getQueueCount();
        $logger->logNotice('Worker: Processed '.$processed.' tasks ('.$errors.' errors). took '.(number_format($time, 4,'.','')).'s. '.$queueCount.' tasks left (gc:'.gc_collect_cycles().')');
        $start = microtime(true);
        $processed = 0;
        $errors = 0;
        $time = 0;
    }
    
    sleep(1);
} while(true);