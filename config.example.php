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
     *   'disableErrorLog' => (optional) true to not log errors (url not found, 404 error, ..)
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
     * oAuth2 / API v3 version!
     * 
     * get analytics metric (pageviews, visits, ...) of a given url or set of urls
     * you need a registered oAuth2 application on the server/task side,
     * and an Analytics Account and a refresh token on the client side
     * 
     * this task requires the Google api client library in your include_path!
     * 
     * register your oAuth2 application here to get a consumerKey/Secret
     * https://code.google.com/apis/console/
     * 
     * You can retrieve a refresh token here:
     * https://code.google.com/oauthplayground/
     * first, click the configuration button, check "Use your own OAuth credentials"
     * and add your client ID/secret, then
     * select Analytics on the left, click "Authorize APIs", then "Exchange authorization code for tokens"
     * use the refresh token on the client side when queueing the analytics task
     * 
     * params:
     * an array with
     *   'metric' => 'the metric to recieve (without the "ga:"-prefix, e.g. 'pageviews', 'visits', ...)',
     *   'pagePath' => 'the (absolute) path to get pageviews for (e.g. /blog/my-post/) / can be a regular expression for some operators'
     *   'hostname' => 'the hostname to filter for. (optional, e.g. example.com)'
     *   'refreshToken' => 'the oAuth2 refresh token'
     *   'profileId' => 'the Google Analytics profile ID'
     *   'operator' => 'the filter operator (not URL encoded). optional, default is '==' (see http://code.google.com/apis/analytics/docs/gdata/gdataReferenceDataFeed.html#filters)
     *   'dateFrom' => 'the start date (optional), format YYYY-MM-DD, default is 2005-01-01
     *   'dateTo' => 'the end date (optional), format YYYY-MM-DD, default is current day
     *   'bulkCacheTime' => do a bulk request, cache the result for this many seconds and filter the result locally (optional, default = 0)
     *                      using a bulk-cache will reduce the number of google API requests, but may result in slower execution and older data
     *   'bulkCacheSplitDays' => optional, default false; split the bulk requests in date-ranges of bulkCacheSplitDays each and merge them afterwards.
     *                           this is to prevent sampling of the google data recommended value depends on actual page soze and age.
     *                           keep in mind that large date-ranges result in many requests, so for a dateFrom 1 month ago, you should use a
     *                           bulkCacheSplitDays of 10 days or more, not recommended for ranges > 2 month
     * 
     * options:
     *   'clientKey' => 'the client key'
     *   'clientSecret' => 'the client secret'
     */
    $config['tasks']['gametric'] = array('\\CacheQueue\\Task\\Analytics', 'getMetric', array(
        'clientKey' => 'yourkey.apps.googleusercontent.com',
        'clientSecret' => 'YourClientSecret'
    ));
    
    /*
     * get analytics pageviews of a given url or set of urls
     * 
     * @deprecated
     * same as gametric task with metric 'pageviews'
     */
    $config['tasks']['pageviews'] = array('\\CacheQueue\\Task\\Analytics', 'getPageviews', array(
        'clientKey' => 'yourkey.apps.googleusercontent.com',
        'clientSecret' => 'YourClientSecret'
    ));
    
    /*
     * get analytics visits of a given url or set of urls
     * 
     * @deprecated
     * same as gametric task with metric 'visits'
     */
    $config['tasks']['visits'] = array('\\CacheQueue\\Task\\Analytics', 'getVisits', array(
        'clientKey' => 'yourkey.apps.googleusercontent.com',
        'clientSecret' => 'YourClientSecret'
    ));
    
    /*
     * oAuth2 / API v3 version!
     * 
     * get analytics eventdata (count and score) of a specific event
     * you need a registered oAuth2 application on the server/task side,
     * and an Analytics Account and a refresh token on the client side
     * 
     * this task requires the Google api client library in your include_path!
     * 
     * register your oAuth2 application here to get a consumerKey/Secret
     * https://code.google.com/apis/console/
     * 
     * You can retrieve a refresh token here:
     * https://code.google.com/oauthplayground/
     * first, click the configuration button, check "Use your own OAuth credentials"
     * and add your client ID/secret, then
     * select Analytics on the left, click "Authorize APIs", then "Exchange authorization code for tokens"
     * use the refresh token on the client side when queueing the analytics task
     * 
     * params:
     * an array with
     *   'eventCategory' => 'the event category'
     *   'eventAction' => 'the event action'
     *   'refreshToken' => 'the oAuth2 refresh token'
     *   'profileId' => 'the Google Analytics profile ID'
     *   'dateFrom' => 'the start date (optional), format YYYY-MM-DD, default is 2005-01-01
     *   'dateTo' => 'the end date (optional), format YYYY-MM-DD, default is current day
     * 
     * options:
     *   'clientKey' => 'the client key'
     *   'clientSecret' => 'the client secret'
     */
    $config['tasks']['eventdata'] = array('\\CacheQueue\\Task\\Analytics', 'getEventData', array(
        'clientKey' => 'yourkey.apps.googleusercontent.com',
        'clientSecret' => 'YourClientSecret'
    ));

    /*
     * oAuth2 / API v3 version!
     * 
     * get list of urls ith the most pageviews
     * you need a registered oAuth2 application on the server/task side,
     * and an Analytics Account and a refresh token on the client side
     * 
     * this task requires the Google api client library in your include_path!
     * 
     * register your oAuth2 application here to get a consumerKey/Secret
     * https://code.google.com/apis/console/
     * 
     * You can retrieve a refresh token here:
     * https://code.google.com/oauthplayground/
     * first, click the configuration button, check "Use your own OAuth credentials"
     * and add your client ID/secret, then
     * select Analytics on the left, click "Authorize APIs", then "Exchange authorization code for tokens"
     * use the refresh token on the client side when queueing the analytics task
     * 
     * params:
     * an array with
     *   'pathPrefix' => 'only consider urls beginning with this prefix. optional, default is "/"',
     *   'hostname' => 'the hostname to filter for. (optional, e.g. example.com)',
     *   'count' => 'limit results to this number (overwrites count option)',
     *   'dateFrom' => 'only consider pageviews newer than his date (format Y-m-d). optional, default is 2005-01-01.',
     *   'dateTo' => 'only consider pageviews older than his date (format Y-m-d). optional, default is the current day.'
     *   'refreshToken' => 'the oAuth2 refresh token'
     *   'profileId' => 'the Google Analytics profile ID'
     * 
     * options:
     *   'clientKey' => 'the client key'
     *   'clientSecret' => 'the client secret',
     *   'count' => 'limit results to this number (can be overwritten by the count parameter)'
     */
    $config['tasks']['topurls'] = array('\\CacheQueue\\Task\\Analytics', 'getTopUrls', array(
        'clientKey' => 'yourkey.apps.googleusercontent.com',
        'clientSecret' => 'YourClientSecret',
        'count' => 20
    ));
    
    /*
     * oAuth2 / API v3 version!
     * 
     * get list of keywords with the most pageviews
     you need a registered oAuth2 application on the server/task side,
     * and an Analytics Account and a refresh token on the client side
     * 
     * this task requires the Google api client library in your include_path!
     * 
     * register your oAuth2 application here to get a consumerKey/Secret
     * https://code.google.com/apis/console/
     * 
     * You can retrieve a refresh token here:
     * https://code.google.com/oauthplayground/
     * first, click the configuration button, check "Use your own OAuth credentials"
     * and add your client ID/secret, then
     * select Analytics on the left, click "Authorize APIs", then "Exchange authorization code for tokens"
     * use the refresh token on the client side when queueing the analytics task
     * 
     * params:
     * an array with
     *   'pathPrefix' => 'only consider urls beginning with this prefix. optional, default is "/"',
     *   'hostname' => 'the hostname to filter for. (optional, e.g. example.com)',
     *   'count' => 'limit results to this number (overwrites count option)',
     *   'dateFrom' => 'only consider pageviews newer than his date (format Y-m-d). optional, default is 2005-01-01.',
     *   'dateTo' => 'only consider pageviews older than his date (format Y-m-d). optional, default is the current day.'
     *   'refreshToken' => 'the oAuth2 refresh token'
     *   'profileId' => 'the Google Analytics profile ID'
     * 
     * options:
     *   'clientKey' => 'the client key'
     *   'clientSecret' => 'the client secret',
     *   'count' => 'limit results to this number (can be overwritten by the count parameter)'
     */
    $config['tasks']['topkeywords'] = array('\\CacheQueue\\Task\\Analytics', 'getTopKeywords', array(
        'clientKey' => 'yourkey.apps.googleusercontent.com',
        'clientSecret' => 'YourClientSecret',
        'count' => 100
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
     * run an SQL query (using PDO)
     * 
     * if the 'return' parameter is not defined, the task returns true on success or false on failure
     * for return = 'rowCount', returns the number of rows affected by the last DELETE, INSERT, or UPDATE statement
     * for return = 'column', returns a single column
     * for return = 'row', returns a row column
     * for return = 'all', returns all rows as array
     * 
     * params:
     * an array with
     *   'query' => 'a valid SQL statement' (can contain placeholders)
     *   'parameter' => an array with the placeholder values (optional)
     *   'return' => rowCount, column, row or all (optional)
     *   'fetchStyle' => only for return = 'row' or return = 'all', the PDO::FETCH_STYLE (optional, default = PDO::FETCH_ASSOC)
     *   'fetchArgument' => only for return = 'all' and certain fetchStyles (optional, see http://www.php.net/manual/de/pdostatement.fetchall.php)
     *   'column' => only for return = 'column', the column number to return (optional, default = 0)
     * 
     *   'dns' => 'a valid PDO DNS' (optional, overwrites the config-dns, see http://www.php.net/manual/de/pdo.connections.php)
     *   'user' => 'username for the sql user' (optional, overwrites the config-user), 
     *   'pass' => 'password for the sql user' (optional, overwrites the config-pass, 
     *   'options' => 'additional driver options' (optional, overwrites the config-options)
     *   
     *   
     * 
     * options:
     * an array with
     *   'dns' => 'a valid PDO DNS' (see http://www.php.net/manual/de/pdo.connections.php)
     *   'user' => 'username for the sql user'
     *   'pass' => 'password for the sql user'
     *   'options' => 'additional driver options' (optional)
     */
    $config['tasks']['pdo'] = array('\\CacheQueue\\Task\\PDO', 'execute', array(
        'dns' => 'mysql:host=localhost;dbname=test',
        'user' => 'root',
        'pass' => 'rootpass',
        'options' => array()
    ));
    
    /*
     * run multiple SQL query (using PDO)
     * 
     * works the same way as the single query task, except that the parameters 
     * query, parameter, return, fetchStyle, fetchArgument and column must be arrays
     * where the queries are grouped by array-key (can be numeric or string keys)
     * the result is an array of the single results
     * 
     * example for the params:
     * array(
     *   'query' => array('foobar'=>'SELECT foo, bar FROM baz WHERE foobar = ?', 'count'=>'SELECT COUNT(*) FROM baz', 'del'=>'DELETE FROM bar WHERE abc > ?'),
     *   'parameter => array('foobar' => array('foobaz'), 'del' => array(1337)), //no parameter for second query
     *   'return => array('foobar' => 'all', 'count' => 'column', 'del' => 'rowCount')
     * )
     * possible response:
     * array(
     *   'foobar' => array(array('foo'=>'foo1','bar'=>'bar1'),array('foo'=>'foo2','bar'=>'bar2')),
     *   'count' => 987,
     *   'del' => 125
     * );
     * 
     * 
     * if the 'return' parameter is not defined, the task returns true on success or false on failure
     * for return = 'rowCount', returns the number of rows affected by the last DELETE, INSERT, or UPDATE statement
     * for return = 'column', returns a single column
     * for return = 'row', returns a row column
     * for return = 'all', returns all rows as array
     * 
     * params:
     * an array with
     *   'query' => 'a valid SQL statement' (can contain placeholders)
     *   'parameter' => an array with the placeholder values (optional)
     *   'return' => rowCount, column, row or all (optional)
     *   'fetchStyle' => only for return = 'row' or return = 'all', the PDO::FETCH_STYLE (optional, default = PDO::FETCH_ASSOC)
     *   'fetchArgument' => only for return = 'all' and certain fetchStyles (optional, see http://www.php.net/manual/de/pdostatement.fetchall.php)
     *   'column' => only for return = 'column', the column number to return (optional, default = 0)
     * 
     *   'dns' => 'a valid PDO DNS' (optional, overwrites the config-dns, see http://www.php.net/manual/de/pdo.connections.php)
     *   'user' => 'username for the sql user' (optional, overwrites the config-user), 
     *   'pass' => 'password for the sql user' (optional, overwrites the config-pass, 
     *   'options' => 'additional driver options' (optional, overwrites the config-options)
     *   
     *   
     * 
     * options:
     * an array with
     *   'dns' => 'a valid PDO DNS' (see http://www.php.net/manual/de/pdo.connections.php)
     *   'user' => 'username for the sql user'
     *   'pass' => 'password for the sql user'
     *   'options' => 'additional driver options' (optional)
     */
    $config['tasks']['pdomulti'] = array('\\CacheQueue\\Task\\PDO', 'execute', array(
        'dns' => 'mysql:host=localhost;dbname=test',
        'user' => 'root',
        'pass' => 'rootpass',
        'options' => array()
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
  
    //settings for APCProxy
    /*
     * add APC for caching of frequently accessed data
     * recommended if there are far more reads (get) than writes (set, queue,outdate,remove)
     */
    $config['connection'] = array(
        'prefix' => 'cc_', //prefix to use for the apc keys
        'filterTags' => false, //false or array() of tags, only entries with one or more of these tags will be stored in APC
        'filterRegex' => false, //false or regular expression as string ( e.g. "^(foo|ba[rz])_[0-9]{6,8}$" ) only those entries whose key matches that expression will be stored in APC
        'connectionClass' => '\\CacheQueue\\Connection\\Mongo', //class of the real connection to use (Mongo, MySQL, Redis, ..)
        'connectionFile' => 'CacheQueue/Connection/Mongo.php', //this file will be included to load the connection class. you can remove this if you use an autoloader
        'connectionConfig' => array( //the complete config-array of the real connection (Mongo, MySQL, Redis, ..) / example for Mongo:
            'server' => 'mongodb://localhost:27017',
            'database' => 'cache_queue',
            'collection' => 'cache',
            'safe' => true,
            'dboptions' => array()
        )
    );
    
    //settings for mongodb
    /*
     * your cache collection should have indixes 'queued' => 1, 'fresh_until' => 1, 'persistent' => 1, 'queue_fresh_until' => 1, 'queue_persistent' => 1, 'tags' => 1, 'queue_priority' => 1
     * run \CacheQueue\Connection\Mongo->setup() to generate these indices or add them manually
     */  
    /*
    $config['connection'] = array(
        //'server' => 'mongodb://[username:password@]host1[:port1]', //optional, default is 'mongodb://localhost:27017' (see http://de3.php.net/manual/en/mongo.construct.php)
        'database' => 'cache_queue',
        'collection' => 'cache',
        'safe' => true,
        'dboptions' => array(
            //'timeout' => 2000,
            //'username' => 'username',
            //'password' => 'password'
        )
    );
     */
    
    //settings for MySQL
    /*
     * your cache table must be created before using this connection
     * run \CacheQueue\Connection\MySQL->setup() to generate the table
     */  
    /*
    $config['connection'] = array(
        'dns' => 'mysql:host=localhost;dbname=test', //a valid PDO DNS (see http://www.php.net/manual/de/pdo.connections.php)
        'user' => 'root',
        'pass' => 'rootpass',
        'table' => 'cache',
        'options' => array(
            //additional database/driver specific options
        )
    );
    */
    
    
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
        'file' => dirname(__FILE__).'/cachequeue_log.txt',
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
        'connection' => '\\CacheQueue\\Connection\\APCProxy', //'\\CacheQueue\\Connection\\Mongo' or '\\CacheQueue\\Connection\\MySQL' or '\\CacheQueue\\Connection\\Redis' or '\\CacheQueue\\Connection\\Dummy'
        'client' => '\\CacheQueue\\Client\\Basic',
        'worker' => '\\CacheQueue\\Worker\\Basic',
        'logger' => '\\CacheQueue\\Logger\\File' // OR '\\CacheQueue\\Logger\\Graylog'
    );