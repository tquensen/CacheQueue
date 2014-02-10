#!/usr/bin/php
<?php

//init composer autoloader
$loader = require __DIR__.'/vendor/autoload.php';

//define config file
$configFile = __DIR__.'/config.php';

$config = array();
require_once($configFile);

$factory = new \CacheQueue\Factory\Factory($config);

try {
    $client = $factory->getClient();
} catch (Exception $e) {
    echo 'Error getting CacheQueue Client: '.$e->getMessage();
}
$result = $client->getOrQueue('example_data', 'store', 'random '.  rand(100, 999), 10);
echo 'Client: result '.($result === false ? 'false' : $result).'.'."\n";