#!/usr/bin/php
<?php

// initiate and/or get the client class,
// use this when you have access to the full CacheQueue lib and config file
function getCacheQueueClient() {
    static $client = null;
    
    if ($client) {
        return $client;
    }
    
    
    //define config file
    $configFile = dirname(__FILE__).'/config.php';

    
    $config = array();
    require_once($configFile);

    $connectionClass = $config['classes']['connection'];
    $clientClass = $config['classes']['client'];
    
    //load required classes - uncomment if you don't use an autoloader 
    /*
    $connectionFile = str_replace('\\', DIRECTORY_SEPARATOR, trim($connectionClass, '\\')).'.php';
    $clientFile = str_replace('\\', DIRECTORY_SEPARATOR, trim($clientClass, '\\')).'.php';

    //add CacheQueue parent folder to include path
    set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__).'/lib');
    require_once('CacheQueue/IConnection.php');
    require_once('CacheQueue/IClient.php');
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
    require_once($filePath.'/IConnection.php');
    require_once($filePath.'/IClient.php');
    require_once($filePath.'/MongoConnection.php');
    require_once($filePath.'/Client.php');   

    //define your connection settings manually. 
    //this must match the server side connection configuration to work 
    $connection = new \CacheQueue\MongoConnection(array(
        'database' => 'cache_queue',
        'collection' => 'cache'
    ));
    $client = new \CacheQueue\Client($connection);
    
    return $client;
}


$client = getCacheQueueClient();
$result = $client->getOrQueue('example_data', 'store', 'random '.  rand(100, 999), time() + 10);
echo 'Client: result '.($result === false ? 'false' : $result).'.'."\n";