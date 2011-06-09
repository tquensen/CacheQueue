<?php
$config = array();

//remove all cache that is outdated (not fresh) for more than cleanupTime seconds
$config['cleanupTime'] = 60 * 60 * 24;

$config['classes'] = array(
    'connection' => '\\CacheQueue\\MongoConnection',
    'client' => '\\CacheQueue\\Client',
    'worker' => '\\CacheQueue\\Worker',
    'logger' => '\\CacheQueue\\FileLogger' //'\\CacheQueue\\GraylogLogger'
);

//mongo
$config['connection'] = array(
    'database' => 'cache_queue',
    'collection' => 'cache'
);

$config['tasks'] = array(
    'store' => '\\CacheQueue\\Task\\Store'
);

//GraylogLogger
/*
$config['logger'] = array(
    'gelfFile' => 'GELF/gelf.php',
    'graylogHostname' => 'graylog2.example.com',
    'graylogPort' => 12201,
    'host' => 'CacheQueueServer'
);
 */

//FileLogger
$config['logger'] = array(
    'file' => dirname(__FILE__).'/log.txt'
);