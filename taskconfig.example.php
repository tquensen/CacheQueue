<?php

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
 * 
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
 * you need a twitter app to use this.
 * https://dev.twitter.com/
 * 
 * params:
 * an array with
 *   'screen_name' => 'the twitter screen_name (optional if user_id is provided)''
 *   'user_id' => 'the twitter user_id (optional if screen_name is provided)'
 *   'limit' => 'max number of results (optional, default 30)'
 *   'filter' => 'a regular expression to fiter the tweets for (optional, default none)' //example: "/#blog/i"
 *   'update' => 'true to keep old entries if not enough new data, false to completely overwrite old data. (optional, default false)'
 * 
 * options:
 *   'consumerKey' => 'the consumer key of your twitter application'
 *   'consukerSecret' => 'the consumer secret of your twitter application'
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
 *   'applicationName' => 'name of your application'
 *   'clientKey' => 'the client key'
 *   'clientSecret' => 'the client secret'
 */
$config['tasks']['gametric'] = array('\\CacheQueue\\Task\\Analytics', 'getMetric', array(
    'applicationName' => 'yourApp',
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
    'applicationName' => 'yourApp',
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
    'applicationName' => 'yourApp',
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
 * 
 * get piwik metrics
 * returns an array with responding metric data or false if the request fails
 * 
 * params:
 * an array with
 *   'action' => 'the API-method to request (the part after "Action." (e.g. "get", "getPageUrl", "getPageUrls", ... see http://piwik.org/docs/analytics-api/reference/#Actions) 
 *   'period' => 'the period',
 *   'date' => 'the date',
 *   'segment' => 'the segment to filter for (optional, see http://piwik.org/docs/analytics-api/segmentation/),
 *   'idSite' => 'the id of your site (optional, overwrites the idSite-option)',
 *   'parameter' => additional parameters for the API as string (url=foo&whatever=bar) or as array(key => value), optional / example: array('pageUrl' => 'http%3A%2F%2Fexample.com%2Fpath%2F', 'expanded' => '1')
 *   'returnSingle' => true or false (optional, default=false) if true, returns only the first result row, useful for 1-row-responses like "getPageUrl", "getPageTitle" or with filter_truncate=0,
 *   'token' => 'your API token (token_auth) (optional if the token is supplied as option)'
 * 
 * options:
 *   'piwikUrl' => 'url to your piwik installation'
 *   'token' => 'your API token (token_auth) (optional if the token is supplied as parameter)'
 *   'idSite' => 'id of your site' (default="all")
 *   'timeout' => 'number of seconds for a connection to wait for response (optional, default=15)'
 */
$config['tasks']['piwik'] = array('\\CacheQueue\\Task\\Piwik', 'doAction', array(
    'piwikUrl' => 'https://piwik.example.com/',
    'token' => '1234518881c0d5289e5feb3b0795b696',
    //'idSite' => '1'
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