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
 *   'format' => '(optional), either 'json' to json_decode the value before saved, or 'xml' to docode as xml and save as SimpleXMLElement'
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
 * get the xing shares of a url
 * params:
 * the absolute URL to get xing shares for as string
 */
$config['tasks']['xingshares'] = array('\\CacheQueue\\Task\\Social', 'getXingShares');


/*
 * get twitter timeline
 * you need a twitter app to use this.
 * https://dev.twitter.com/
 * 
 * params:
 * an array with
 *   'screen_name' => 'the twitter screen_name (optional if user_id is provided)'
 *   'user_id' => 'the twitter user_id (optional if screen_name is provided)'
 *   'limit' => 'max number of results (optional, default 30)'
 *   'filter' => 'a regular expression to fiter the tweets for (optional, default none)' //example: "/#blog/i"
 *   'update' => 'true to keep old entries if not enough new data, false to completely overwrite old data. (optional, default false)'
 * 
 * options:
 *   'consumerKey' => 'the consumer key of your twitter application'
 *   'consumerSecret' => 'the consumer secret of your twitter application'
 */
$config['tasks']['timeline'] = array('\\CacheQueue\\Task\\Social', 'getTwitterTimeline', array(
    'consumerKey' => 'the consumer key of your twitter application',
    'consumerSecret' => 'the consumer secret of your twitter application'
));

/*
 * get twitter search results
 * you need a twitter app to use this.
 * https://dev.twitter.com/
 * 
 * params: all available params at https://dev.twitter.com/docs/api/1.1/get/search/tweets
 * an array with any valid parameter available at https://dev.twitter.com/docs/api/1.1/get/search/tweets
 * only required parameter is the search query "q" (parameters MUST NOT be URL-Encoded)
 * 
 * options:
 *   'consumerKey' => 'the consumer key of your twitter application'
 *   'consumerSecret' => 'the consumer secret of your twitter application'
 */
$config['tasks']['twittersearch'] = array('\\CacheQueue\\Task\\Social', 'getTwitterSearchResults', array(
    'consumerKey' => 'the consumer key of your twitter application',
    'consumerSecret' => 'the consumer secret of your twitter application'
));

/*
 * oAuth2 / API v3 version!
 * 
 * get analytics metric (pageviews, visits, ...) of a given url or set of urls
 * you need a registered oAuth2 application on the server/task side,
 * and an Analytics Account and a refresh token on the client side
 * 
 * this task requires the "google/apiclient": "1.0.*" in you composer.json
 * 
 * register your oAuth2 application here to get a consumerKey/Secret
 * https://code.google.com/apis/console/
 * 
 * You can retrieve a refresh token here:
 * https://developers.google.com/oauthplayground/
 * first, click the configuration button, check "Use your own OAuth credentials"
 * and add your client ID/secret, then
 * select "Google Analytics API v3" on the left and choose "https://www.googleapis.com/auth/analytics.readonly", click "Authorize APIs", then "Exchange authorization code for tokens"
 * use the refresh token on the client side when queueing the analytics task
 * 
 * params:
 * an array with
 *   'metric' => 'the metric to recieve (without the "ga:"-prefix, e.g. 'pageviews', 'visits', ...)',
 *   'pagePath' => 'the (absolute) path to get pageviews for (e.g. /blog/my-post/) / can be a regular expression for some operators'
 *   'hostname' => 'the hostname to filter for. (optional, e.g. example.com)'
 *   'operator' => 'the filter operator for the pagePath (not URL encoded). optional, default is '==' (see https://developers.google.com/analytics/devguides/reporting/core/v3/reference#filters)
 *   'refreshToken' => 'the oAuth2 refresh token'
 *   'profileId' => 'the Google Analytics profile ID'
 *   'dateFrom' => 'the start date (optional), format YYYY-MM-DD, default is 2005-01-01
 *   'dateTo' => 'the end date (optional), format YYYY-MM-DD, default is current day
 *   'splitDays' => optional, default false; split the request in date-ranges of splitDays each and merge them afterwards.
 *                  this is to prevent sampling of the google data. recommended value depends on actual page size and age.
 *                  keep in mind that large date-ranges result in many requests, so for a dateFrom 1 month ago, you should use a
 *                  splitDays of 10 days or more, not recommended for ranges > 2 month
 *   'bulkCacheTime' => do a bulk request, cache the result for this many seconds and filter the result locally (optional, default = 0)
 *                      using a bulk-cache will reduce the number of google API requests, but may result in slower execution and older data
 *   'bulkCacheFilters' => a filter rule for the bulkcache (example: "ga:hostname==example.com;ga:pagePath=~^/blog/", optional), the bulkcache will contain all urls (see https://developers.google.com/analytics/devguides/reporting/core/v3/reference#filters)
 *   'bulkCacheSplitDays' => optional, default false; split the bulk requests in date-ranges of bulkCacheSplitDays each and merge them afterwards.
 *                           this is to prevent sampling of the google data. recommended value depends on actual page size and age.
 *                           keep in mind that large date-ranges result in many requests, so for a dateFrom 1 month ago, you should use a
 *                           bulkCacheSplitDays of 10 days or more, not recommended for ranges > 2 month
 * 
 * options:
 *   'applicationName' => 'name of your application'
 *   'clientKey' => 'the client key'
 *   'clientSecret' => 'the client secret'
 *   'googleConfigIniLocation' => 'location of the google client config.ini file' (optional)
 */
$config['tasks']['gametric'] = array('\\CacheQueue\\Task\\Analytics', 'getMetric', array(
    'applicationName' => 'yourApp',
    'clientKey' => 'yourkey.apps.googleusercontent.com',
    'clientSecret' => 'YourClientSecret',
    'googleConfigIniLocation' => null
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
    'clientSecret' => 'YourClientSecret',
    'googleConfigIniLocation' => null
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
    'clientSecret' => 'YourClientSecret',
    'googleConfigIniLocation' => null
));

/*
 * oAuth2 / API v3 version!
 * 
 * get analytics eventdata (count and score) of a specific event
 * you need a registered oAuth2 application on the server/task side,
 * and an Analytics Account and a refresh token on the client side
 * 
 * this task requires the "google/apiclient": "1.0.*" in you composer.json
 * 
 * register your oAuth2 application here to get a consumerKey/Secret
 * https://code.google.com/apis/console/
 * 
 * You can retrieve a refresh token here:
 * https://developers.google.com/oauthplayground/
 * first, click the configuration button, check "Use your own OAuth credentials"
 * and add your client ID/secret, then
 * select "Google Analytics API v3" on the left and choose "https://www.googleapis.com/auth/analytics.readonly", click "Authorize APIs", then "Exchange authorization code for tokens"
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
 *   'googleConfigIniLocation' => 'location of the google client config.ini file' (optional)
 */
$config['tasks']['eventdata'] = array('\\CacheQueue\\Task\\Analytics', 'getEventData', array(
    'clientKey' => 'yourkey.apps.googleusercontent.com',
    'clientSecret' => 'YourClientSecret',
    'googleConfigIniLocation' => null
));

/*
 * oAuth2 / API v3 version!
 * 
 * get list of urls ith the most pageviews/visits
 * you need a registered oAuth2 application on the server/task side,
 * and an Analytics Account and a refresh token on the client side
 * 
 * this task requires the "google/apiclient": "1.0.*" in you composer.json
 * 
 * register your oAuth2 application here to get a consumerKey/Secret
 * https://code.google.com/apis/console/
 * 
 * You can retrieve a refresh token here:
 * https://developers.google.com/oauthplayground/
 * first, click the configuration button, check "Use your own OAuth credentials"
 * and add your client ID/secret, then
 * select "Google Analytics API v3" on the left and choose "https://www.googleapis.com/auth/analytics.readonly", click "Authorize APIs", then "Exchange authorization code for tokens"
 * use the refresh token on the client side when queueing the analytics task
 * 
 * params:
 * an array with
 *   'pagePath' => 'the (absolute) path to get pageviews for (e.g. /blog/my-post/) / can be a regular expression for some operators'
 *   'hostname' => 'the hostname to filter for. (optional, e.g. example.com)'
 *   'operator' => 'the filter operator for the pagePath (not URL encoded). optional, default is '==' (see https://developers.google.com/analytics/devguides/reporting/core/v3/reference#filters)
 *   'count' => 'limit results to this number (overwrites count option)',
 *   'metric' => 'the metric to use (pageviews, uniquePageviews, visits, .., default = pageviews)',
 *   'dateFrom' => 'only consider pageviews newer than his date (format Y-m-d). optional, default is 2005-01-01.',
 *   'dateTo' => 'only consider pageviews older than his date (format Y-m-d). optional, default is the current day.'
 *   'refreshToken' => 'the oAuth2 refresh token'
 *   'profileId' => 'the Google Analytics profile ID'
 * 
 * options:
 *   'clientKey' => 'the client key'
 *   'clientSecret' => 'the client secret',
 *   'count' => 'limit results to this number (can be overwritten by the count parameter)'
 *   'googleConfigIniLocation' => 'location of the google client config.ini file' (optional)
 */
$config['tasks']['topurls'] = array('\\CacheQueue\\Task\\Analytics', 'getTopUrls', array(
    'clientKey' => 'yourkey.apps.googleusercontent.com',
    'clientSecret' => 'YourClientSecret',
    'count' => 20,
    'googleConfigIniLocation' => null
));

/*
 * oAuth2 / API v3 version!
 * 
 * get list of keywords with the most pageviews/visits
 * you need a registered oAuth2 application on the server/task side,
 * and an Analytics Account and a refresh token on the client side
 * 
 * this task requires the "google/apiclient": "1.0.*" in you composer.json
 * 
 * register your oAuth2 application here to get a consumerKey/Secret
 * https://code.google.com/apis/console/
 * 
 * You can retrieve a refresh token here:
 * https://developers.google.com/oauthplayground/
 * first, click the configuration button, check "Use your own OAuth credentials"
 * and add your client ID/secret, then
 * select "Google Analytics API v3" on the left and choose "https://www.googleapis.com/auth/analytics.readonly", click "Authorize APIs", then "Exchange authorization code for tokens"
 * use the refresh token on the client side when queueing the analytics task
 * 
 * params:
 * an array with
 *   'pagePath' => 'the (absolute) path to get pageviews for (e.g. /blog/my-post/) / can be a regular expression for some operators'
 *   'hostname' => 'the hostname to filter for. (optional, e.g. example.com)'
 *   'operator' => 'the filter operator for the pagePath (not URL encoded). optional, default is '==' (see https://developers.google.com/analytics/devguides/reporting/core/v3/reference#filters)
 *   'count' => 'limit results to this number (overwrites count option)',
 *   'metric' => 'the metric to use (pageviews, uniquePageviews, visits, .., default = pageviews)',
 *   'dateFrom' => 'only consider pageviews newer than his date (format Y-m-d). optional, default is 2005-01-01.',
 *   'dateTo' => 'only consider pageviews older than his date (format Y-m-d). optional, default is the current day.'
 *   'refreshToken' => 'the oAuth2 refresh token'
 *   'profileId' => 'the Google Analytics profile ID'
 * 
 * options:
 *   'clientKey' => 'the client key'
 *   'clientSecret' => 'the client secret',
 *   'count' => 'limit results to this number (can be overwritten by the count parameter)'
 *   'googleConfigIniLocation' => 'location of the google client config.ini file' (optional)
 */
$config['tasks']['topkeywords'] = array('\\CacheQueue\\Task\\Analytics', 'getTopKeywords', array(
    'clientKey' => 'yourkey.apps.googleusercontent.com',
    'clientSecret' => 'YourClientSecret',
    'count' => 100,
    'googleConfigIniLocation' => null
));

/*
 * oAuth2 / API v3 version!
 *
 * perform a custom analytics api request
 * you need a registered oAuth2 application on the server/task side,
 * and an Analytics Account and a refresh token on the client side
 *
 * this task requires the "google/apiclient": "1.0.*" in you composer.json
 *
 * register your oAuth2 application here to get a consumerKey/Secret
 * https://code.google.com/apis/console/
 *
 * You can retrieve a refresh token here:
 * https://developers.google.com/oauthplayground/
 * first, click the configuration button, check "Use your own OAuth credentials"
 * and add your client ID/secret, then
 * select "Google Analytics API v3" on the left and choose "https://www.googleapis.com/auth/analytics.readonly", click "Authorize APIs", then "Exchange authorization code for tokens"
 * use the refresh token on the client side when queueing the analytics task
 *
 * params:
 * an array with
 *   'profileId' => 'the Google Analytics profile ID',
 *   'refreshToken' => 'the oAuth2 refresh token'
 *   'metrics' => 'list of metrics e.g. 'ga:sessions,ga:pageviews' ',
 *   'dateFrom' => 'the start date (optional), format YYYY-MM-DD, default is 2005-01-01
 *   'dateTo' => 'the end date (optional), format YYYY-MM-DD, default is current day
 *   'dimensions' => (optional) 'A comma-separated list of Analytics dimensions. E.g., 'ga:browser,ga:city''
 *   'filters' => (optional) 'A comma-separated list of dimension or metric filters to be applied to Analytics data.'
 *   'sort' => (optional) 'A comma-separated list of dimensions or metrics that determine the sort order for Analytics',
 *   'maxResults' => (optional) 'The maximum number of entries to include in this feed.',
 *   'startIndex' => (optional) 'An index of the first entity to retrieve. Use this parameter as a pagination mechanism along with the max-results parameter.'
 *   'segment' => (optional) 'An Analytics segment to be applied to data.',
 *   'samplingLevel' => (optional) 'The desired sampling level.'
 *
 * options:
 *   'applicationName' => 'name of your application'
 *   'clientKey' => 'the client key'
 *   'clientSecret' => 'the client secret'
 *   'googleConfigIniLocation' => 'location of the google client config.ini file' (optional)
 *
 * returns:
 *   array(
 *     'totalResults' => number of total results,
 *     'totalsForAllResults' => array of aggregated totals for each metric
 *     'containsSampledData' => boolean indication if results are based on sampled data
 *     'results' => array of individual result rows, each containing an array of metric/dimension => value pairs
 *   )
 */
$config['tasks']['analytics'] = array('\\CacheQueue\\Task\\Analytics', 'customRequest', array(
    'applicationName' => 'yourApp',
    'clientKey' => 'yourkey.apps.googleusercontent.com',
    'clientSecret' => 'YourClientSecret',
    'googleConfigIniLocation' => null
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
 * call the mailchimp api
 * 
 * @see http://apidocs.mailchimp.com/api/2.0/
 * 
 * this task requires the "mailchimp/mailchimp": ">=2.0.0" in you composer.json
 * also, add the mailchimp repository to your composer.json:
 * "repositories": [
 *      {
 *          "type": "vcs",
 *          "url": "https://bitbucket.org/mailchimp/mailchimp-api-php"
 *      }
 *  ],
 * 
 * params:
 * an array with
 *  'method' => 'the API method to call',
 *  'parameter' => required parameter for the method (optional, depends on method)
 *                  as array('parameter_name' => 'parameter_value')
 *                  see http://apidocs.mailchimp.com/api/1.3/#method-&-error-code-docs
 *
 *  'apiKey' => 'your mailchimp api key' (optional, overwrites the apiKey option)
 *  'options' => array of options for the mailchimp client (optional, overwrites/merges the options option)
 * 
 * options:
 *   'apiKey' => 'your mailchimp api key'
 *   'options' => array of options for the mailchimp client
 */
$config['tasks']['mailchimp'] = array('\\CacheQueue\\Task\\MailChimp', 'execute', array(
    'apiKey' => 'your mailchimp api key',
    'timeout' => 300,
    'options' => array(
        //'timeout' => 300, //server call timeout in seconds, default=600
        //'debug' => false,
        //'ssl_verifypeer' => null,
        //'ssl_verifyhost' => null,
        //'ssl_cainfo' => null
    )
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