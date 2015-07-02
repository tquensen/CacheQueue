<?php
/*
 * simple REST API endpoint
 *
 * responses are in json format
 * 
 * You have to provide a valid API Key as X-Api-Key HTTP Header
 *
 * allowed methods:
 * 
 * GET /entry/[KEY] to get the cache entry with the given key. use the ?onlyFresh=true query parameter to return only fresh entries
 * response:
 *   if the entry was found: {success: true, entry: {entrydata}}
 *   if not found (or if outdated and onlyFresh=true was set: {success: false, entry: false}
 * 
 * GET /tag/[TAG] to get all cached entries with the given tag. use the ?onlyFresh=true query parameter to return only fresh entries
 * response:
 *   if at least one entry was found: {success: true, entries: [{entry1data, entry2data, ...]}
 *   if no entry was found:  {success: false, entries: []}
 * 
 * example: GET /entry/foobar?onlyFresh=true
 * 
 * use api.php/entry/[KEY] and api.php/tag/[TAG] if you don't have mod_rewrite enabled
 * 
 * invalid requests will return 40x status codes with a json body containing the error message: {error: 'error message'}
 * 
 */


//accept request data either as json or application/x-www-form-urlencoded
//GET only, so not needed at the moment
/*
if ((isset($_SERVER['CONTENT_TYPE']) && strpos(strtolower($_SERVER['CONTENT_TYPE']), 'application/json') !== false)
    || (isset($_SERVER['HTTP_CONTENT_TYPE']) && strpos(strtolower($_SERVER['HTTP_CONTENT_TYPE']), 'application/json') !== false)) {
    $requestContent = file_get_contents('php://input');
    $requestParams = json_decode($inputJSON, true);
    if ($requestParams === null) {
        $requestParams = array();
    }
} else {
    $requestParams = $_POST;
}
*/

$requestMethod = !empty($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
$requestAction = !empty($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';

//init composer autoloader
$loader = require __DIR__.'/../vendor/autoload.php';

//define config file
$configFile = __DIR__.'/../config.php';

$config = array();
require_once($configFile);

header('Content-Type: application/json');

if (empty($_SERVER['HTTP_X_API_KEY'])) {
    $response = array('error' => 'This request required a valid API key as X-Api-Key header field');
    header('HTTP/1.1 401 Unauthorized', true, 401);
    echo json_encode($response);exit;
}
$apiKey = $_SERVER['HTTP_X_API_KEY'];

if (empty($config['api']['keys']) || empty($config['api']['keys'][$apiKey])) {
    $response = array('error' => 'Invalid api key');
    header('HTTP/1.1 403 Forbidden', true, 403);
    echo json_encode($response);exit;
}

$userRights = $config['api']['keys'][$apiKey];

$factory = new \CacheQueue\Factory\Factory($config);

$client = $factory->getClient();

$action = $requestMethod.' '.$requestAction;
$matches = null;
switch (true) {

    //check for access right violations
    case preg_match('|^GET |', $action) && !in_array('GET', $userRights):
        $response = array('error' => 'No permission to access this ressource');
        header('HTTP/1.1 403 Forbidden', true, 403);
        break;

    
    //check matching routes
    case preg_match('|^GET /entry/(.+)$|', $action, $matches):
        $entry = $client->getEntry($matches[1], !empty($_GET['onlyFresh']));
        if ($entry) {
            $response = array('success' => true, 'entry' => $entry);
        } else {
            $response = array('success' => false, 'entry' => false);
        }
        break;
    case preg_match('|^GET /tag/(.+)$|', $action, $matches):
        $entries = $client->getEntriesByTag($matches[1], !empty($_GET['onlyFresh']));
        $response = array('success' => count($entries) > 0, 'entries' => array_values($entries));
        break;

    //no matching route found
    default:
        $response = array('error' => 'Unknown ressource and/or request method');
        header('HTTP/1.1 404 Not Found', true, 404);

}

echo json_encode($response);exit;