#!/usr/bin/php
<?php
//only available via command line (this file shold be outside the web folder anyway)
if (empty($_SERVER['argc'])) {
    die();
}

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__).'/lib');

$configFile = dirname(__FILE__).'/config.php';

$config = array();
require_once($configFile);

$connectionClass = $config['classes']['connection'];
$loggerClass = $config['classes']['logger'];


//method 1) load autoloader and register CacheQueue classes
require_once('SplClassLoader/SplClassLoader.php');
$classLoader = new SplClassLoader('CacheQueue');
$classLoader->register();   

//method 2) load required classes - uncomment if you don't use an autoloader 
/*
$connectionFile = str_replace('\\', DIRECTORY_SEPARATOR, trim($connectionClass, '\\')).'.php';
$loggerFile = str_replace('\\', DIRECTORY_SEPARATOR, trim($loggerClass, '\\')).'.php';

require_once('CacheQueue/Exception/Exception.php');
require_once('CacheQueue/Logger/LoggerInterface.php');
require_once('CacheQueue/Connection/ConnectionInterface.php');
require_once($loggerFile);
require_once($connectionFile);
*/


$logger = new $loggerClass($config['logger']);
$connection = new $connectionClass($config['connection']);

if (empty($_SERVER['argv'][1])) {
    print_help();
    exit;
}

try {
    switch (strtolower($_SERVER['argv'][1])) {
        case 'remove':
            if (empty($_SERVER['argv'][2])) {
                echo 'Error: a valid key or "all" required as first parameter!'."\n";
                exit;
            } else {
                $key = trim($_SERVER['argv'][2]);
            }
            if (!empty($_SERVER['argv'][3])) {
                switch (strtolower(trim($_SERVER['argv'][3]))) {
                    case 'force':
                        $force = true;
                        $persistent = null;
                        break;
                    case 'persistent':
                        $force = true;
                        $persistent = true;
                        break;
                    case 'nonpersistent':
                        $force = true;
                        $persistent = false;
                        break;
                    default:
                        echo 'Unknown option "'.$_SERVER['argv'][3].'", valid options are "force", "persistent" and "nonpersistent" '."\n";
                        exit;
                }
            } else {
                $force = false;
                $persistent = null;
            }
            if (trim(strtolower($key)) == 'all') {
                $status = $connection->removeAll($force, $persistent);
                echo 'Removing all matching entries: '.($status ? 'OK' : 'ERROR')."\n";
            } else {
                $status = $connection->remove($key, $force, $persistent);
                echo 'Removing entry "'.$_SERVER['argv'][1].'": '.($status ? 'OK' : 'ERROR')."\n";
            }
            break;
        case 'outdate':
            if (empty($_SERVER['argv'][2])) {
                echo 'Error: a valid key or "all" required as first parameter!'."\n";
                exit;
            } else {
                $key = trim($_SERVER['argv'][2]);
            }
            if (!empty($_SERVER['argv'][3])) {
                switch (strtolower(trim($_SERVER['argv'][3]))) {
                    case 'force':
                        $force = true;
                        $persistent = null;
                        break;
                    case 'persistent':
                        $force = true;
                        $persistent = true;
                        break;
                    case 'nonpersistent':
                        $force = true;
                        $persistent = false;
                        break;
                    default:
                        echo 'Unknown option "'.$_SERVER['argv'][3].'", valid options are "force", "persistent" and "nonpersistent" '."\n";
                        exit;
                }
            } else {
                $force = false;
                $persistent = null;
            }
            if (trim(strtolower($key)) == 'all') {
                $status = $connection->outdateAll($force, $persistent);
                echo 'Outdating all matching entries: '.($status ? 'OK' : 'ERROR')."\n";
            } else {
                $status = $connection->outdate($key, $force, $persistent);
                echo 'Outdating entry "'.$_SERVER['argv'][1].'": '.($status ? 'OK' : 'ERROR')."\n";
            }
            break;
         default:
            echo 'Unknown task "'.$_SERVER['argv'][1].'"'."\n";
            break;
    }
} catch (Exception $e) {
    echo 'Error: '.$e."\n";
}

function print_help()
{
    echo <<<EOF
Available Tasks:
    remove KEY|ALL [force|persistent|nonpersistent]
        removes an entry with the key KEY (or all entries if KEY=ALL) from cache
        options: if no option is given, removes only outdated, non persistent entries
                 if "force", removes any entries regardless of freshness or persistent state
                 if "persistent", removes only matching persistent entries
                 if "nonpersistent", removes only non persistent entries
                 
    outdate KEY|ALL [force|persistent|nonpersistent]
        outdates an entry with the key KEY (or all entries if KEY=ALL) (sets fresh_until to the past)
        options: if no option is given, outdates only fresh, non persistent entries
                 if "force", outdates any entries regardless of freshness or persistent state
                 if "persistent", outdates only matching persistent entries
                 if "nonpersistent", outdates only non persistent entries
                 
EOF;
}