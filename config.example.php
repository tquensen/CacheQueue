<?php
$config = array();

// --- GENERAL SETTINGS --- //

    //configuration for the worker.php, worker_fg.php and worker_bg.php scripts
    $config['general']['workerscript_noticeAfterMoreThanSeconds'] = 30; //log a "finished" message only after X seconds
    $config['general']['workerscript_noticeAfterTasksCount'] = 100; //log a message after proceeding X tasks without pause
    $config['general']['workerscript_noticeSlowTaskMoreThanSeconds'] = 5; //log a "status" message if a single task takes longer than X seconds
    $config['general']['workerscript_noticeLargeDataMoreThanBytes'] = 102400; //log a "status" message if a single task data is larger than X bytes (1024=1KB, 1048576=1MB)
    $config['general']['workerscript_bg_exitAfterTasksCount'] = 100; //exit bg script after 100 Tasks without break
    $config['general']['workerscript_bg_exitAfterMoreThanSeconds'] = 60; //exit bg script after 60 seconds without a break

// --- TASKS --- //  
    
    include 'taskconfig.php';

// --- MAIN CLASSES --- //   

    //define the classes you want to use as connectin, client, server and logger
    $config['classes'] = array(
        'connection' => '\\CacheQueue\\Connection\\APCProxy', //'\\CacheQueue\\Connection\\Mongo' or '\\CacheQueue\\Connection\\MySQL' or '\\CacheQueue\\Connection\\Dummy' // '\\CacheQueue\\Connection\\Redis' is not ready
        'client' => '\\CacheQueue\\Client\\Basic',
        'worker' => '\\CacheQueue\\Worker\\Basic',
        'logger' => '\\CacheQueue\\Logger\\File' // OR '\\CacheQueue\\Logger\\Graylog' OR '\\CacheQueue\\Logger\\Sentry'
    );

// --- CLIENT SETTINGS --- //      
    
    $config['client'] = array(); //BASIC client class doesn't need configuration
    
// --- WORKER SETTINGS --- //      
    
    $config['worker'] = array(); //BASIC worker class doesn't need configuration    
    
// --- CONNECTION SETTINGS --- //  
  
    
    //settings for mongodb
    /*
     * run \CacheQueue\Connection\Mongo->setup() to generate indices
     */  
    $mongoConnection = array(
        //'server' => 'mongodb://[username:password@]host1[:port1]', //optional, default is 'mongodb://localhost:27017' (see http://de3.php.net/manual/en/mongo.construct.php)
        'database' => 'cache_queue',
        'collection' => 'cache',
        'w' => 1,
        'dboptions' => array(
            //'wTimeout' => 2000,
            //'username' => 'username',
            //'password' => 'password'
        )
    );
     
    
    //settings for MySQL
    /*
     * your cache table must be created before using this connection
     * run \CacheQueue\Connection\MySQL->setup() to generate the table
     */  
    $mySQLConnection = array(
        'dns' => 'mysql:host=localhost;dbname=test', //a valid PDO DNS (see http://www.php.net/manual/de/pdo.connections.php)
        'user' => 'root',
        'pass' => 'rootpass',
        'table' => 'cache',
        'options' => array(
            //additional database/driver specific options
        ),
        'useFulltextTags' => false //uses innoDB FULLTEXT index, requires MySQL 5.6+ - recommended (This can not be changed afterthe table was generated!)
    );
    
    
    
    //settings for redis / predis //ALPHA / NOT WORKING
    /*
     * for parameters, see see https://github.com/nrk/predis/wiki/Quick-tour
     */
    $redisConnection = array(
        'predisFile' => 'Predis/Predis.php', //this file will be included to load the Predis classes
        'parameters' => array(
            'host' => '10.211.55.4', 
            'port' => 6379, 
        ),
        'options' => array(
            'prefix' => 'cachequeue'
        )
    );
     
    //settings for APCProxy
    /*
     * add APC for caching of frequently accessed data
     * recommended if there are far more reads (get) than writes (set, queue,outdate,remove)
     */
    $apcProxyConnection = array(
        'prefix' => 'cc_', //prefix to use for the apc keys
        'filterTags' => false, //false or array() of tags, only entries with one or more of these tags will be stored in APC
        'filterRegex' => false, //false or regular expression as string ( e.g. "^(foo|ba[rz])_[0-9]{6,8}$" ) only those entries whose key matches that expression will be stored in APC
        'connectionClass' => '\\CacheQueue\\Connection\\Mongo', //class of the real connection to use (Mongo, MySQL, Redis, ..)
        'connectionFile' => 'CacheQueue/Connection/Mongo.php', //this file will be included to load the connection class. you can remove this if you use an autoloader
        'connectionConfig' => $mongoConnection
    );
    
    $config['connection'] = $apcProxyConnection;
    

// --- LOGGER SETTINGS --- //  
    
    //FileLogger
    $fileLogger = array(
        'file' => dirname(__FILE__).'/cachequeue_log.txt',
        'showPid' => false, //display the process ID in the logfile - useful when multiple workers are running
        'logLevel' => 6 // LOG_NONE = 0, LOG_DEBUG = 1, LOG_NOTICE = 2, LOG_ERROR = 4, LOG_ALL = 7 or a combination (LOG_ERROR and LOG_NOTICE = 6)
    );

    //GraylogLogger
    //requires gelf-php lib: in composer.json, require { "graylog2/gelf-php": "~1.0" }
    $graylogLogger = array(
        'graylogHostname' => 'graylog2.example.com',
        'graylogPort' => 12201,
        'host' => 'CacheQueueServer',
        'showPid' => false, //display the process ID in the logfile - useful when multiple workers are running
        'logLevel' => 6 // LOG_NONE = 0, LOG_DEBUG = 1, LOG_NOTICE = 2, LOG_ERROR = 4, LOG_ALL = 7 or a combination (LOG_ERROR and LOG_NOTICE = 6)
    );
    
    //SentryLogger
    //requires raven-php lib: in composer.json, require { "raven/raven": "0.8.*@dev" }
    $sentryLogger = array(
        'sentryDSN' => 'http://public:secret@example.com/1',
        'options' => array( //options to pass to the raven client
            'name' => 'CacheQueueServer',
            'tags' => array(
                'php_version' => phpversion(),
            ),
        ), 
        'registerErrorHandler' => false, //set the Raven_ErrorHandler as error handler
        'registerExceptionHandler' => false, //set the Raven_ErrorHandler as exception handler
        'showPid' => false, //display the process ID in the logfile - useful when multiple workers are running
        'logLevel' => 6 // LOG_NONE = 0, LOG_DEBUG = 1, LOG_NOTICE = 2, LOG_ERROR = 4, LOG_ALL = 7 or a combination (LOG_ERROR and LOG_NOTICE = 6)
    );

    $config['logger'] = $fileLogger;
