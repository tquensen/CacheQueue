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
     * the defined method is called with the parameters $params, $config, $job and $worker
     */
    $config['tasks'] = array();
    
    /*
     * stores/caches the given params as data
     * params:
     * any string, number, bool or array to store under the given key
     */
    $config['tasks']['store'] = array('\\CacheQueue\\Task\\Misc', 'store');
    
    /*
     * reads and stores the content of a url
     * params:
     * an array of
     *   'url' => 'the absolute URL (or anything which is readable by fopen) to get the content from (see http://www.php.net/manual/en/wrappers.php)'
     *   'context' => '(optional), an array of context options to use or false/null to nut use a context (see http://www.php.net/manual/en/function.stream-context-create.php)'
     *   'format' => '(optional), only valid option is json - when set, the response is processed by json_decode before saved'
     */
    $config['tasks']['loadurl'] = array('\\CacheQueue\\Task\\Misc', 'loadUrl');

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
     * get twitter timeline
     * 
     * params:
     * an array with
     *   'account' => 'the twitter account name'
     *   'limit' => 'max number of results (optional, default 30)'
     *   'filter' => 'a regular expression to fiter the tweets for (optional, default none)' //example: "/#blog/i"
     *   'update' => 'true to keep old entries if not enough new data, false to completely overwrite old data. (optional, default false)'
     */
    $config['tasks']['timeline'] = array('\\CacheQueue\\Task\\Social', 'getTwitterTimeline');
    
    /*
     * get analytics pageviews of a given url
     * you need a registered oAuth application on the server/task side,
     * and an Analytics Account, a token and a token secret on the client side
     * 
     * this task requires the Zend Framework in your include_path!
     * 
     * register your oAuth application here to get a consumerKey/Secret
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
     *   'pagePath' => 'the (absolute) path to get pageviews for (e.g. /blog/my-post/) / can be a regular expression for some operators'
     *   'hostname' => 'the hostname to filter for. (optional, e.g. example.com)'
     *   'token' => 'the oAuth token'
     *   'tokenSecret' => 'the oAuth token secret',
     *   'profileId' => 'the Google Analytics profile ID'
     *   'operator' => 'the filter operator (not URL encoded). optional, default is '==' (see http://code.google.com/apis/analytics/docs/gdata/gdataReferenceDataFeed.html#filters)
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
     *   'pathPrefix' => 'only consider urls beginning with this prefix. optional, default is "/"',
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
     * run a Symfony 1.x task
     * 
     * the key will save the status code of the Symfony task.
     * 
     * params:
     *   the Symfony cli command as string (Example: "cc", "doctrine:build --all --and-load")
     * 
     * options:
     *   'symfonyBaseDir' => 'full path to your symfony base'
     *     (usually where your symfony cli file is located, 
     *      example: "/var/www/mySymfonyProject")
     */
    $config['tasks']['symfony'] = array('\\CacheQueue\\Task\\Symfony', 'runTask', array(
        'symfonyBaseDir' => "/var/www/mySymfonyProject"
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
    /*
     * your cache collection should have indixes 'queued' => 1, 'fresh_until' => 1, 'persistent' => 1, 'queue_fresh_until' => 1 and 'queue_persistent' => 1
     * run \CacheQueue\MongoConnection->setup() to generate these indices or add them manually
     */  
    
    $config['connection'] = array(
        //'server' => 'mongodb://[username:password@]host1[:port1]', //optional, default is 'mongodb://localhost:27017' (see http://de3.php.net/manual/en/mongo.construct.php)
        'database' => 'cache_queue',
        'collection' => 'cache',
        'safe' => false,
        'dboptions' => array(
            //'timeout' => 2000,
            //'username' => 'username',
            //'password' => 'password'
        )
    );
    
    
    //settings for redis / predis
    /*
     * for parameters, see see https://github.com/nrk/predis/wiki/Quick-tour
     */
    /*
    $config['connection'] = array(
        'predisFile' => 'Predis/Predis.php', //this file will be included to load the Predis classes. you can remove this if you use an autoloader
        'parameters' => array(
            'host' => '10.211.55.4', 
            'port' => 6379, 
        ),
        'options' => array(
            'prefix' => 'cachequeue'
        )
    );
     */

// --- LOGGER SETTINGS --- //  
    
    //FileLogger
    $config['logger'] = array(
        'file' => dirname(__FILE__).'/log.txt',
        'showPid' => false //display the process ID in the logfile - useful when multiple workers are running
    );

    //GraylogLogger
    /*
    $config['logger'] = array(
        'gelfFile' => 'GELF/gelf.php', //this file will be included to load the gelf classes. you can remove this if you use an autoloader
        'graylogHostname' => 'graylog2.example.com',
        'graylogPort' => 12201,
        'host' => 'CacheQueueServer',
        'showPid' => false //display the process ID in the logfile - useful when multiple workers are running
    );
     */


// --- MAIN CLASSES --- //   

    //define the classes you want to use as connectin, client, server and logger
    $config['classes'] = array(
        'connection' => '\\CacheQueue\\Connection\\Mongo', //or '\\CacheQueue\\Connection\\Redis' or '\\CacheQueue\\Connection\\Dummy'
        'client' => '\\CacheQueue\\Client\\Basic',
        'worker' => '\\CacheQueue\\Worker\\Basic',
        'logger' => '\\CacheQueue\\Logger\\File' // OR '\\CacheQueue\\Logger\\Graylog'
    );