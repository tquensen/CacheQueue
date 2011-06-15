<?php
$config = array();

// --- GENERAL SETTINGS --- //


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
     * get analytics pageviews of a given url
     * you need a registered oAuth application on the server/task side,
     * and an Analytics Account, a token and a token secret on the client side
     * 
     * this task requires the Zend Framework in your include_path!
     * 
     * register your oAuth registration here to get a consumerKey/Secret
     * https://www.google.com/accounts/ManageDomains
     * 
     * You can retrieve an oauthToken and tokenSecret here:
     * http://googlecodesamples.com/oauth_playground/index.php
     * choose Analytics as scope, select HMAC-SHA1 as signature method,
     * fill in your consumerKey and consumerSecret, then get and authorize a Request Token,
     * and upgrade to an access token.
     * use this access token and tken secret on the client side when queueing the analytics task
     * 
     * params:
     * an array with
     *   'url' => 'the url to get pageviews for'
     *   'token' => 'the oAuth token'
     *   'tokenSecret' => 'the oAuth token secret',
     *   'profileId' => 'the Google Analytics profile ID'
     * 
     * options:
     *   'consumerKey' => 'the consumer key'
     *   'consumerSecret' => 'theconsumer secret'
     */
    $config['tasks']['pageviews'] = array('\\CacheQueue\\Task\\Analytics', 'getPageviews', array(
        'consumerKey' => 'your.key',
        'consumerSecret' => 'YourConsumerSecret'
    ));
    
    /*
     * get list of urls ith the most pageviews
     * you need a registered oAuth application on the server/task side,
     * and an Analytics Account, a token and a token secret on the client side
     * 
     * this task requires the Zend Framework in your include_path!
     * 
     * register your oAuth registration here to get a consumerKey/Secret
     * https://www.google.com/accounts/ManageDomains
     * 
     * You can retrieve an oauthToken and tokenSecret here:
     * http://googlecodesamples.com/oauth_playground/index.php
     * choose Analytics as scope, select HMAC-SHA1 as signature method,
     * fill in your consumerKey and consumerSecret, then get and authorize a Request Token,
     * and upgrade to an access token.
     * use this access token and tken secret on the client side when queueing the analytics task
     * 
     * params:
     * an array with
     *   'url' => 'only consider urls beginning with this prefix',
     *   'count' => 'limit results to this number (overwrites count option)',
     *   'dateFrom' => 'only consider pageviews newer than his date (format Y-m-d). optional, default is 2005-01-01.',
     *   'dateTo' => 'only consider pageviews older than his date (format Y-m-d). optional, default is the current day.'
     *   'token' => 'the oAuth token'
     *   'tokenSecret' => 'the oAuth token secret',
     *   'profileId' => 'the Google Analytics profile ID'
     * 
     * options:
     *   'consumerKey' => 'the consumer key'
     *   'consumerSecret' => 'theconsumer secret',
     *   'count' => 'limit results to this number (can be overwritten by the count parameter)'
     */
    $config['tasks']['topurls'] = array('\\CacheQueue\\Task\\Analytics', 'getTopUrls', array(
        'consumerKey' => 'your.key',
        'consumerSecret' => 'YourConsumerSecret',
        'count' => 20
    ));
    
    /*
     * this is an fully featured example of a task declaration
     * copy&paste this for your own tasks ;)
     * 
     * params:
     * an array with
     *   'foo' => 'the foo'
     *   'bar' => 'some bar or baz'
     * 
     * options:
     * an array with
     *   'some' => 'options'
     *   'for' => 'this task'
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
        'collection' => 'cache',
        'safe' => false
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