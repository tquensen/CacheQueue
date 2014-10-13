#!/usr/bin/php
<?php
//only available via command line (this file shold be outside the web folder anyway)
if (empty($_SERVER['argc'])) {
    die();
}
    
//init composer autoloader
$loader = require __DIR__.'/vendor/autoload.php';

//define config file
$configFile = __DIR__.'/config.php';

$config = array();
require_once($configFile);


$factory = new \CacheQueue\Factory\Factory($config);

$connection = $factory->getConnection();

if (empty($_SERVER['argv'][1])) {
    print_help();
    exit;
}

try {
    if (empty($_SERVER['argv'][2])) {
        if (strtolower($_SERVER['argv'][1]) == 'setup' || strtolower($_SERVER['argv'][1]) == 'clearqueue' || strtolower($_SERVER['argv'][1]) == 'status') {
            $key = null;
        } elseif (strtolower($_SERVER['argv'][1]) == 'count') {
            $key = 'ALL';    
        } elseif (strtolower($_SERVER['argv'][1]) == 'cleanup') {    
            echo 'Error: outdated-time required as first parameter'."\n";
            echo 'Examples:'."\n";
            echo 'cleanup 3600'."\t".'removes all entries that are outdated for at least 1 hour'."\n";
            echo 'cleanup 86400'."\t".'removes all entries that are outdated for at least 1 day'."\n";
            echo 'cleanup 604800'."\t".'removes all entries that are outdated for at least 1 week'."\n";
            exit;
        } else {
            echo 'Error: a valid key, "tag" or "all" required as first parameter!'."\n";
            exit;
        }
    } else {
        $key = trim($_SERVER['argv'][2]);
    }
    if (trim(strtolower($key)) == 'tag') {
        if (empty($_SERVER['argv'][3])) {
            echo 'Error: a tag is required as second parameter!'."\n";
            exit;
        }
        $tag = trim($_SERVER['argv'][3]);
        $option = !empty($_SERVER['argv'][4]) ? $_SERVER['argv'][4] : null;
    } else {
        $option = !empty($_SERVER['argv'][3]) ? $_SERVER['argv'][3] : null;
    }
    if (!empty($option)) {
        if (strtolower($_SERVER['argv'][1]) == 'count') {
            switch (strtolower(trim($option))) {
                case 'fresh':
                    $fresh = true;
                    break;
                case 'outdated':
                    $fresh = false;
                    break;
                default:
                    echo 'Unknown option "'.$option.'", valid options are "fresh" and "outdated" '."\n";
                    exit;
            }
        } else {
            switch (strtolower(trim($option))) {
                case 'force':
                    $force = true;

                    break;
                default:
                    echo 'Unknown option "'.$option.'", valid options are "force" '."\n";
                    exit;
            }
        }
    } else {
        if (strtolower($_SERVER['argv'][1]) == 'count') {
            $fresh = null;
        } else {
            $force = false;
        }
    }

    switch (strtolower($_SERVER['argv'][1])) {
        case 'get':      
            if (trim(strtolower($key)) == 'tag') {
                $results = $connection->getByTag($tag, $force ? false : true);
                 echo 'Cache entries for tag "'.$tag.'" '.($force ? 'all' : 'fresh').' found: '.count($results)."\n";
                 foreach ($results as $data) {
                    echo "\n".'Data for entry "'.$data['key'].'":'."\n";
                    echo "\t".'is fresh:       '.($data['is_fresh'] ? 'yes' : 'no')."\n";
                    echo "\t".'fresh until:    '.date('Y-m-d H:i:s', $data['fresh_until'])."\n";
                    echo "\t".'tags:       '.implode(', ', $data['tags'])."\n";
                    echo "\t".'queue is fresh: '.($data['queue_is_fresh'] ? 'yes' : 'no')."\n";
                    echo "\t".'queue fresh until: '.date('Y-m-d H:i:s', $data['queue_fresh_until'])."\n";
                    echo "\t".'data:       '.print_r($data['data'], true)."\n";
                }     
            } else {
                $data = $connection->get($key);
                if (!$data) {
                    echo 'Cache entry "'.$key.'" not found'."\n";
                } else {
                    echo 'Data for entry "'.$key.'":'."\n";
                    echo "\t".'is fresh:       '.($data['is_fresh'] ? 'yes' : 'no')."\n";
                    echo "\t".'fresh until:    '.date('Y-m-d H:i:s', $data['fresh_until'])."\n";
                    echo "\t".'tags:       '.implode(', ', $data['tags'])."\n";
                    echo "\t".'queue is fresh: '.($data['queue_is_fresh'] ? 'yes' : 'no')."\n";
                    echo "\t".'queue fresh until: '.date('Y-m-d H:i:s', $data['queue_fresh_until'])."\n";
                    echo "\t".'data:       '.print_r($data['data'], true)."\n";
                }     
            }
            break;
        case 'getdata':
            if (trim(strtolower($key)) == 'tag') {
                $results = $connection->getByTag($tag, $force ? false : true);
                 echo 'Cache entries for tag "'.$tag.'" '.($force ? 'all' : 'fresh').' found: '.count($results)."\n";
                 foreach ($results as $data) {
                    echo "\n".'Data for entry "'.$data['key'].'":'."\n";
                    echo print_r($data['data'], true)."\n";
                }
            } else {
                $data = $connection->get($key);
                if (!$data) {
                    echo 'Cache entry "'.$key.'" not found'."\n";
                } else {
                    echo 'Data for entry "'.$key.'":'."\n";
                    echo print_r($data['data'], true)."\n";
                }
            }
            break;
        case 'count':
            if (trim(strtolower($key)) == 'all') {
                $count = $connection->countAll($fresh);
                echo $count.($option ? ' '.$option : '').' entries found.'."\n"; 
            } else {
                if (strtolower($key) != 'tag') {
                    $tag = $key;
                }
                $count = $connection->countByTag($tag, $fresh);
                echo $count.($option ? ' '.$option : '').' entries with tag "'.$tag.'" found.'."\n"; 
            }
            break;
        case 'remove':      
            if (trim(strtolower($key)) == 'all') {
                $status = $connection->removeAll($force);
                echo 'Removing all matching entries: '.($status ? 'OK' : 'ERROR')."\n";
            } elseif (trim(strtolower($key)) == 'tag') {
                $status = $connection->removeByTag($tag, $force);
                echo 'Removing all matching entries with tag "'.$tag.'": '.($status ? 'OK' : 'ERROR')."\n";
            } else {
                $status = $connection->remove($key, $force);
                echo 'Removing entry "'.$key.'": '.($status ? 'OK' : 'ERROR')."\n";
            }
            break;
        case 'outdate':
            if (trim(strtolower($key)) == 'all') {
                $status = $connection->outdateAll($force);
                echo 'Outdating all matching entries: '.($status ? 'OK' : 'ERROR')."\n";
            } elseif (trim(strtolower($key)) == 'tag') {
                $status = $connection->outdateByTag($tag, $force);
                echo 'Outdating all matching entries with tag "'.$tag.'": '.($status ? 'OK' : 'ERROR')."\n";
            } else {
                $status = $connection->outdate($key, $force);
                echo 'Outdating entry "'.$key.'": '.($status ? 'OK' : 'ERROR')."\n";
            }
            break;
        case 'cleanup':
            $status = $connection->cleanup((int) $key);
            echo 'Removing all entries that are outdated for at least '.(int) $key.' seconds: '.($status ? 'OK' : 'ERROR')."\n";
            break;
        case 'setup':
            if (method_exists($connection, 'setup')) {
                echo 'Running $connection->setup();'."\n";
                $connection->setup();
            } else {
                echo 'Your current connection ('.get_class($connection).') has no setup-method;'."\n";
            }
            break;
        case 'clearqueue':
            $status = $connection->clearQueue();
            echo 'Removing all entries from Queue: '.($status ? 'OK' : 'ERROR')."\n";
            break;        
        case 'status':
            echo 'CacheQueue status:'."\n";
                $queueCount = $connection->getQueueCount();
                $entryCount = $connection->countAll();
                $freshCount = $connection->countAll(true);
                $outdatedCount = $connection->countAll(false);
                echo $entryCount . ' cached entries ('.$freshCount.' fresh, '.$outdatedCount.' outdated).'."\n";
                echo $queueCount . ' tasks currently in queue.'."\n";
            break;    
        default:
            echo 'Unknown task "'.$_SERVER['argv'][1].'"'."\n";
            break;
    }
} catch (Exception $e) {
    echo 'Error: '.$e."\n";
}

echo "\n";

function print_help()
{
    echo <<<EOF
Available Tasks:
    get KEY|TAG [tag]
        displays the cache information and data for the given cache entry or tag

    getdata KEY|TAG [tag]
        displays the stored data for the given cache entry or tag

    remove KEY|TAG [tag]|ALL [force]
        removes an entry with the key KEY (or all entries with the TAG [tag] or all entries if KEY=ALL) from cache
        options: if no option is given, removes only outdated entries
                 if "force", removes any entries regardless of freshness
                 
    outdate KEY|TAG [tag]|ALL [force]
        outdates an entry with the key KEY (or all entries with the TAG [tag] or all entries if KEY=ALL) (sets fresh_until to the past)
        options: if no option is given, outdates only fresh entries
                 if "force", outdates any entries regardless of freshness
    
    count ALL|TAG [tag] [fresh|outdated]
        gets the number of all matching entries (or all matching entries with the TAG [tag])
        options: if no option is given, all entries are counted
                 if "fresh", only fresh entries are counted
                 if "outdated", only outdated entries are counted
    
    clearqueue   removes all entries from queue
    
    cleanup SEC  removes all cached entries that are outdated for at least SEC seconds
                 (3600 = 1 hour, 86400 = 1 day, 604800 = 1 week)
    
    setup        run the setup-method of the connection class (if available)
    
    status       show status information


EOF;
}