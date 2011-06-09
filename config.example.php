<?php
$config = array();

// --- GENERAL SETTINGS --- //

    //remove all cache that is outdated (not fresh) for more than cleanupTime seconds
    $config['cleanupTime'] = 60 * 60 * 24;


// --- TASKS --- //    

    // define your tasks here
    // 
    // Syntax: 'taskname' => array('Classname', 'method') 
    //      OR 'taskname' => 'Classname' to use the method 'execute'
    // the defined method is called with the parameters $params and $job
    $config['tasks'] = array(
        'store' => '\\CacheQueue\\Task\\Store',
        'twitter_retweets' => array('\\CacheQueue\\Task\\Twitter', 'getRetweets')
    );


// --- CONNECTION SETTINGS --- //  
  
    //settings for mongodb
    $config['connection'] = array(
        'database' => 'cache_queue',
        'collection' => 'cache'
    );


// --- LOGGER SETTINGS --- //  
    
    //FileLogger
    $config['logger'] = array(
        'file' => dirname(__FILE__).'/log.txt'
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


// --- MAIN CLASSES --- //   

    //define the classes you want to use as connectin, client, server and logger
    $config['classes'] = array(
        'connection' => '\\CacheQueue\\MongoConnection',
        'client' => '\\CacheQueue\\Client',
        'worker' => '\\CacheQueue\\Worker',
        'logger' => '\\CacheQueue\\FileLogger' // OR '\\CacheQueue\\GraylogLogger'
    );