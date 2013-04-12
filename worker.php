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
$worker = $factory->getWorker();

//log a "finished" message only after X seconds
$noticeAfterMoreThanSeconds = 30;

//log a message after proceeding X tasks without pause
$noticeAfterTasksCount = 100; //notice after the 100th, 200th, 300th, ... Task without break

if (in_array('--debug', $argv)) {
    $debug = true;
} else {
    $debug = false;
}

$start = microtime(true);
$time = 0;
$processed = 0;
$errors = 0;
do {   
    try {
        
        do {          
            $ts = microtime(true);
            $status = null;
            try {
                if ($job = $worker->getJob()) {
                    if ($debug) echo 'running job '.$job['key']. ' ('.$job['task'].')'."\n";
                    $worker->work($job); 
                    if ($debug) echo 'done...'."\n";
                } else {
                    //pause processing for 1 sec if no queued task was found
                    break;
                }
            } catch (\CacheQueue\Exception\Exception $e) {
                //log CacheQueue exceptions 
                if ($debug) echo $e."\n";
                $errors++;
                $logger->logException($e);
                unset ($e);
            }

            

            $processed++;
            $time += microtime(true) - $ts;
            if ($noticeAfterTasksCount && !($processed % $noticeAfterTasksCount)) {
                $end = microtime(true);
                $count = $connection->getQueueCount();
                $logger->logNotice('Worker: running, processed '.$processed.' tasks ('.$errors.' errors), '.(int)$count.' tasks remaining. took '.(number_format($time, 4,'.','')).'s so far... (gc:'.gc_collect_cycles().')');
            }             
        } while (true);       
        if ($processed) {
            $end = microtime(true);
            if (!$noticeAfterMoreThanSeconds || ($end - $start > $noticeAfterMoreThanSeconds)) {
                $logger->logNotice('Worker: finished, processed '.$processed.' tasks ('.$errors.' errors). took '.(number_format($time, 4,'.','')).'s - ready for new tasks (gc:'.gc_collect_cycles().')');
                $start = microtime(true);
                $processed = 0;
                $errors = 0;
                $time = 0;
            }
        }
    } catch (Exception $e) {
        //handle exceptions
        if ($debug) echo $e."\n";
        $logger->logException($e);
        exit;
    }
    sleep(1); 
} while(true);