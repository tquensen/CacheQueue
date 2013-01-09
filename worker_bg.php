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


//exit after proceeding X tasks
$exitAfterTasksCount = 100; //exit after 100 Tasks without break

$start = microtime(true);
$time = 0;
$processed = 0;
$errors = 0;

try {

    do {          
        $ts = microtime(true);
        $status = null;
        try {
            if ($job = $worker->getJob()) {
                $worker->work($job); 
            } else {
                //done, exit
                break;
            }
        } catch (\CacheQueue\Exception\Exception $e) {
            //log CacheQueue exceptions 
            $errors++;
            $logger->logError('Worker: error '.(string) $e);
            unset ($e);
        }



        $processed++;
        if ($exitAfterTasksCount && $processed >= $exitAfterTasksCount) {
            break; //not finished, exiting anyway to prevent memory leaks...
        }             
    } while (true);       
} catch (Exception $e) {
    //handle exceptions
    $logger->logError('Worker: Exception '.(string) $e);
}
echo "\n".$processed.'|'.$errors;