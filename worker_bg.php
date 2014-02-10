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


//exit after proceeding X tasks
$exitAfterTasksCount = $config['general']['workerscript_bg_exitAfterTasksCount']; //exit after X Tasks without break
$exitAfterMoreThanSeconds = $config['general']['workerscript_bg_exitAfterMoreThanSeconds']; //exit after X seconds without a break

//log a "status" message if a single task takes longer than X seconds
$noticeSlowTaskMoreThanSeconds = $config['general']['workerscript_noticeSlowTaskMoreThanSeconds'];

$start = microtime(true);
$processed = 0;
$errors = 0;

try {

    do {     
        
        try {
            if ($job = $worker->getJob()) {
                $jobStart = microtime(true);
                $worker->work($job); 
                $jobEnd = microtime(true);
                if ($jobEnd - $jobStart > $noticeSlowTaskMoreThanSeconds) {
                    $logger->logNotice('Worker: Job '.$job['key'].' (task '.$job['task'].') took '.(number_format($jobEnd - $jobStart, 4,'.','')).'s.');
                }
            } else {
                //done, exit
                break;
            }
        } catch (\CacheQueue\Exception\Exception $e) {
            //log CacheQueue exceptions 
            $errors++;
            $logger->logException($e);
            unset ($e);
        }
        
        $processed++;
        $end = microtime(true);
        if ($exitAfterTasksCount && $processed >= $exitAfterTasksCount) {
            break; //not finished, exiting anyway to prevent memory leaks...
        } 
        if ($processed && $exitAfterMoreThanSeconds && $end - $start > $exitAfterMoreThanSeconds) {
            break; //not finished, exiting anyway to prevent memory leaks...
        } 
    } while (true);       
} catch (Exception $e) {
    //handle exceptions
    $logger->logException($e);
}
echo "\n".$processed.'|'.$errors;