<?php
$config = array();

// --- GENERAL SETTINGS --- //

    //remove all cache that is outdated (not fresh) for more than cleanupTime seconds
    $config['cleanupTime'] = 60 * 60 * 24;


// --- TASKS --- //    

    /*
     * define your tasks here
     * 
     * Syntax: 'taskname' => array('Classname', 'method') 
     *      OR 'taskname' => array('Classname', 'method', array('some' => 'additional', 'config' => 'parameters)) 
     *      OR 'taskname' => 'Classname' to use the method 'execute' and no config parameters
     * 
     * the defined method is called with the parameters $params, $config and $job
     */
    $config['tasks'] = array();
    
    /*
     * stores/caches the given params as data
     * params:
     * any string, number, bool or array to store under the given key
     */
    $config['tasks']['store'] = '\\CacheQueue\\Task\\Store';
    
    /*
     * get the retweets of a url
     * params:
     * the absolute URL to get twitter retweets for as string
     */
    $config['tasks']['retweets'] = array('\\CacheQueue\\Task\\Social', 'getRetweets');
    
    /*
     * get the likes of a url
     * params:
     * the absolute URL to get facebook likes for as string
     */
    $config['tasks']['likes'] = array('\\CacheQueue\\Task\\Social', 'getLikes');
    
    /*
     * get the google plus one hits of a url
     * params: 
     * the absolute URL to get google plus ones for as string
     */
    $config['tasks']['plusones'] = array('\\CacheQueue\\Task\\Social', 'getPlusOnes');

    /*
     * this is an fully featured example of a task declaration
     * copy&paste this for your own tasks ;)
     * 
     * params:
     * an array with
     *   'foo' => 'the foo'
     *   'bar' => 'some bar or baz'
     */
    $config['tasks']['example'] = array('YourClass', 'yourMethod', array(
        'some' => 'options',
        'for' => 'this task',
        'defaultBar' => 'some bar or baz',
        'login' => 'foobar',
        'password' => 'password'
    ));


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