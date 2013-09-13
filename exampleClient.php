#!/usr/bin/php
<?php

//add CacheQueue parent folder to include path
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__.'/lib');

//define config file
$configFile = __DIR__.'/config.php';

$config = array();
require_once($configFile);

//initialize factory
require_once('CacheQueue/Factory/FactoryInterface.php');
require_once('CacheQueue/Factory/Factory.php');

$factory = new \CacheQueue\Factory\Factory($config);

try {
    $client = $factory->getClient();
} catch (Exception $e) {
    echo 'Error getting CacheQueue Client: '.$e->getMessage();
}
$result = $client->getOrQueue('example_data', 'store', 'random '.  rand(100, 999), 10);
echo 'Client: result '.($result === false ? 'false' : $result).'.'."\n";