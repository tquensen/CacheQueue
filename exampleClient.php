#!/usr/bin/php
<?php

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


$client = getCacheQueueClient();
$result = $client->getOrQueue('example_data', 'store', 'random '.  rand(100, 999), time() + 10);
echo 'Client: result '.($result === false ? 'false' : $result).'.'."\n";