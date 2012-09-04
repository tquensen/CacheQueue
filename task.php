#!/usr/bin/php
<?php
//only available via command line (this file shold be outside the web folder anyway)
if (empty($_SERVER['argc'])) {
    die();
}
    
//add CacheQueue parent folder to include path
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__).'/lib');

//define config file
$configFile = dirname(__FILE__).'/config.php';

$config = array();
require_once($configFile);

//initialize factory
require_once('CacheQueue/Factory/FactoryInterface.php');
require_once('CacheQueue/Factory/Factory.php');

$factory = new \CacheQueue\Factory\Factory($config);

$connection = $factory->getConnection();

if (empty($_SERVER['argv'][1])) {
    print_help();
    exit;
}

try {
    if (empty($_SERVER['argv'][2])) {
        if ($_SERVER['argv'][1] != 'setup') {
            echo 'Error: a valid key, "tag" or "all" required as first parameter!'."\n";
            exit;
        } else {
            $key = null;
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
        switch (strtolower(trim($option))) {
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
                echo 'Unknown option "'.$option.'", valid options are "force", "persistent" and "nonpersistent" '."\n";
                exit;
        }
    } else {
        $force = false;
        $persistent = null;
    }

    switch (strtolower($_SERVER['argv'][1])) {
        case 'get':      
            if (trim(strtolower($key)) == 'tag') {
                $results = $connection->getByTag($tag, $force ? false : true);
                 echo 'Cache entries for tag "'.$tag.'" '.($force ? 'all' : 'fresh').' found: '.count($results)."\n";
                 foreach ($results as $data) {
                    echo "\n".'Data for entry "'.$data['key'].'":'."\n";
                    echo "\t".'is fresh:       '.($data['is_fresh'] ? 'yes' : 'no')."\n";
                    echo "\t".'fresh until:    '.($data['persistent'] ? 'persistent' : date('Y-m-d H:i:s', $data['fresh_until']))."\n";
                    echo "\t".'tags:       '.implode(', ', $data['tags'])."\n";
                    echo "\t".'queue is fresh: '.($data['queue_is_fresh'] ? 'yes' : 'no')."\n";
                    echo "\t".'queue fresh until: '.($data['queue_persistent'] ? 'persistent' : date('Y-m-d H:i:s', $data['queue_fresh_until']))."\n";
                    echo "\t".'data:       '.print_r($data['data'], true)."\n";
                }     
            } else {
                $data = $connection->get($key);
                if (!$data) {
                    echo 'Cache entry "'.$key.'" not found'."\n";
                } else {
                    echo 'Data for entry "'.$key.'":'."\n";
                    echo "\t".'is fresh:       '.($data['is_fresh'] ? 'yes' : 'no')."\n";
                    echo "\t".'fresh until:    '.($data['persistent'] ? 'persistent' : date('Y-m-d H:i:s', $data['fresh_until']))."\n";
                    echo "\t".'tags:       '.implode(', ', $data['tags'])."\n";
                    echo "\t".'queue is fresh: '.($data['queue_is_fresh'] ? 'yes' : 'no')."\n";
                    echo "\t".'queue fresh until: '.($data['queue_persistent'] ? 'persistent' : date('Y-m-d H:i:s', $data['queue_fresh_until']))."\n";
                    echo "\t".'data:       '.print_r($data['data'], true)."\n";
                }     
            }
            break;
        case 'remove':      
            if (trim(strtolower($key)) == 'all') {
                $status = $connection->removeAll($force, $persistent);
                echo 'Removing all matching entries: '.($status ? 'OK' : 'ERROR')."\n";
            } elseif (trim(strtolower($key)) == 'tag') {
                $status = $connection->removeByTag($tag, $force, $persistent);
                echo 'Removing all matching entries with tag "'.$tag.'": '.($status ? 'OK' : 'ERROR')."\n";
            } else {
                $status = $connection->remove($key, $force, $persistent);
                echo 'Removing entry "'.$key.'": '.($status ? 'OK' : 'ERROR')."\n";
            }
            break;
        case 'outdate':
            if (trim(strtolower($key)) == 'all') {
                $status = $connection->outdateAll($force, $persistent);
                echo 'Outdating all matching entries: '.($status ? 'OK' : 'ERROR')."\n";
            } elseif (trim(strtolower($key)) == 'tag') {
                $status = $connection->outdateByTag($tag, $force, $persistent);
                echo 'Outdating all matching entries with tag "'.$tag.'": '.($status ? 'OK' : 'ERROR')."\n";
            } else {
                $status = $connection->outdate($key, $force, $persistent);
                echo 'Outdating entry "'.$_SERVER['argv'][1].'": '.($status ? 'OK' : 'ERROR')."\n";
            }
            break;
        case 'setup':
            if (method_exists($connection, 'setup')) {
                echo 'Running $connection->setup();'."\n";
                $connection->setup();
            } else {
                echo 'Your current connection ('.get_class($connection).') has no setup-method;'."\n";
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
    get KEY|TAG [tag]
        displays the stored data for the given cache entry or tag

    remove KEY|TAG [tag]|ALL [force|persistent|nonpersistent]
        removes an entry with the key KEY (or all entries with the TAG [tag] or all entries if KEY=ALL) from cache
        options: if no option is given, removes only outdated, non persistent entries
                 if "force", removes any entries regardless of freshness or persistent state
                 if "persistent", removes only matching persistent entries
                 if "nonpersistent", removes only non persistent entries
                 
    outdate KEY|TAG [tag]|ALL [force|persistent|nonpersistent]
        outdates an entry with the key KEY (or all entries with the TAG [tag] or all entries if KEY=ALL) (sets fresh_until to the past)
        options: if no option is given, outdates only fresh, non persistent entries
                 if "force", outdates any entries regardless of freshness or persistent state
                 if "persistent", outdates only matching persistent entries
                 if "nonpersistent", outdates only non persistent entries
                 
    setup        run the setuup-method of the connection class (if available)
                 
EOF;
}