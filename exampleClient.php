#!/usr/bin/php
<?php

// initiate and/or get the client class,
// use this when you have access to the full CacheQueue lib and config file
function getCacheQueueClient() {
    static $client = null;
    
    if ($client) {
        return $client;
    }
    
    //add CacheQueue parent folder to include path
    set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__).'/lib');
     
    //define config file
    $configFile = dirname(__FILE__).'/config.php';
    
    $config = array();
    require_once($configFile);

    $connectionClass = $config['classes']['connection'];
    $clientClass = $config['classes']['client'];
 
   
    
    //method 1) load autoloader and register CacheQueue classes
    require_once('SplClassLoader/SplClassLoader.php');
    $classLoader = new SplClassLoader('CacheQueue');
    $classLoader->register();   
    
    //method 2) load required classes - uncomment if you don't use an autoloader 
    /*
    $connectionFile = str_replace('\\', DIRECTORY_SEPARATOR, trim($connectionClass, '\\')).'.php';
    $clientFile = str_replace('\\', DIRECTORY_SEPARATOR, trim($clientClass, '\\')).'.php';

    require_once('CacheQueue/Exception/Exception.php');
    require_once('CacheQueue/Connection/ConnectionInterface.php');
    require_once('CacheQueue/Client/ClientInterface.php');
    require_once($connectionFile);
    require_once($clientFile);
    
    */

    $connection = new $connectionClass($config['connection']);
    $client = new $clientClass($connection);
    
    return $client;
}

// if you dont want to use the full CacheQueue stuff on client side,
// this should be everything you need for the client to work
function simpleGetCacheQueueClient() {
    static $client = null;
    
    if ($client) {
        return $client;
    }
    
    //you only need the connection and the client class and their interfaces
    $filePath = dirname(__FILE__).'/lib/CacheQueue';
    require_once($filePath.'/Exception/ExceptionInterface.php');
    require_once($filePath.'/Connection/ConnectonInterface.php');
    require_once($filePath.'/Client/ClientInterface.php');
    require_once($filePath.'/Connection/Mongo.php'); // or require_once($filePath.'/Connection/Redis.php') or require_once($filePath.'/Connection/Dummy.php');
    require_once($filePath.'/Client/Basic.php');   

    //define your connection settings manually. 
    //this must match the server side connection configuration to work 
    $connection = new \CacheQueue\Connection\Mongo(array(
        'database' => 'cache_queue',
        'collection' => 'cache'
    ));
    $client = new \CacheQueue\Client\Basic($connection);

    return $client;
}

try {
    $client = getCacheQueueClient();
} catch (Exception $e) {
    echo 'Error getting CacheQueue Client: '.$e->getMessage();
}
$result = $client->getOrQueue('example_data', 'store', 'random '.  rand(100, 999), 10);
echo 'Client: result '.($result === false ? 'false' : $result).'.'."\n";