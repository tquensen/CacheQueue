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

$channelName = 'default';
foreach ($argv as $arg) {
    if (strpos('--channel=', $arg) === 0) {
        $channelName = substr($arg, 10);
        break;
    }
}

$factory = new \CacheQueue\Factory\Factory($config);

$logger = $factory->getLogger();
$connection = $factory->getConnection();

$workerFile = __DIR__.'/worker_bg.php';

//log a "status" message only after X seconds
$noticeAfterMoreThanSeconds = $config['general']['workerscript_noticeAfterMoreThanSeconds'];

if (!empty($config['channels'][$channelName]) && (int) $config['channels'][$channelName] > 0) {
    $channel = (int) $config['channels'][$channelName];
} else {
    $logger->logError('Worker: Invalid channel "'.$channelName.'"');
    echo 'Invalid channel "'.$channelName.'"' . "\n";
    exit;
}

$start = microtime(true);
$time = 0;
$processed = 0;
$errors = 0;

do {
    try {
        if ($connection->getQueueCount($channel)) {
            try {
                $ts = microtime(true);
                $response = exec($workerFile . ' --channel='.escapeshellarg($channelName));
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
        $logger->logException($e);
        unset ($e);
    } catch (Exception $e) {
        //handle exceptions
        $logger->logException($e);
        exit;
    }
    
    $end = microtime(true);
    if ($processed && (!$noticeAfterMoreThanSeconds || ($end - $start > $noticeAfterMoreThanSeconds))) {
        $queueCount = $connection->getQueueCount($channel);
        $logger->logNotice('Worker: Processed '.$processed.' tasks ('.$errors.' errors). took '.(number_format($time, 4,'.','')).'s. '.$queueCount.' tasks left (gc:'.gc_collect_cycles().')');
        $start = microtime(true);
        $processed = 0;
        $errors = 0;
        $time = 0;
    }
    sleep(1);
} while(true);