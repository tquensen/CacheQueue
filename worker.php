#!/usr/bin/php
<?php
//only available via command line (this file shold be outside the web folder anyway)
if (empty($_SERVER['argc'])) {
    die();
}
    
//init composer autoloader
$loader = require __DIR__.'/vendor/autoload.php';

//enable PHP 5.3+ garbage collection
gc_enable();

//define config file
$configFile = __DIR__.'/config.php';

$config = array();
require_once($configFile);

$factory = new \CacheQueue\Factory\Factory($config);

$logger = $factory->getLogger();
$connection = $factory->getConnection();
$worker = $factory->getWorker();

//log a "finished" message only after X seconds
$noticeAfterMoreThanSeconds = $config['general']['workerscript_noticeAfterMoreThanSeconds'];

//log a message after proceeding X tasks without pause
$noticeAfterTasksCount = $config['general']['workerscript_noticeAfterTasksCount']; //notice after the 100th, 200th, 300th, ... Task without break

//log a "status" message if a single task takes longer than X seconds
$noticeSlowTaskMoreThanSeconds = $config['general']['workerscript_noticeSlowTaskMoreThanSeconds'];

//log a "status" message if a single task data is larger than X bytes
$noticeLargeDataMoreThanBytes = $config['general']['workerscript_noticeLargeDataMoreThanBytes'];

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
                    $jobStart = microtime(true);
                    $result = $worker->work($job);
                    $jobEnd = microtime(true);
                    if ($noticeLargeDataMoreThanBytes) {
                        $size = strlen(is_array($result) ? serialize($result) : $result);
                        if ($size > $noticeLargeDataMoreThanBytes) {
                            $base = log($size) / log(1024);
                            $suffixes = array('', 'k', 'M', 'G', 'T');
                            $hrSize = round(pow(1024, $base - floor($base)), 2) . $suffixes[floor($base)];
                            $logger->logNotice('Worker: Job '.$job['key'].' (task '.$job['task'].') data size is '.$hrSize.'.');
                        }
                    }
                    if ($jobEnd - $jobStart > $noticeSlowTaskMoreThanSeconds) {
                        $logger->logNotice('Worker: Job '.$job['key'].' (task '.$job['task'].') took '.(number_format($jobEnd - $jobStart, 4,'.','')).'s.');
                    }
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