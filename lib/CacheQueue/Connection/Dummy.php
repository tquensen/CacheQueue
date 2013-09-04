<?php
namespace CacheQueue\Connection;

class Dummy implements ConnectionInterface
{
    private $dbName = null;
    private $collectionName = null;
    
    private $db = null;
    private $collection = null;
    
    private $safe = null;
    
    public function __construct($config = array())
    {
        
    }

    public function get($key)
    {
        return false;
    }
    
    
    
    public function getValue($key, $onlyFresh = false)
    {
        return false;
    }

    public function getJob($workerId)
    {
        return false;
    }
    
    public function updateJobStatus($key, $workerId)
    {
        return false;
    }
    
    public function set($key, $data, $freshFor, $force = false, $tags = array())
    {
        return false;
    }

    public function queue($key, $task, $params, $freshFor, $force = false, $tags = array(), $priority = 50)
    {
        return false;
    }

    public function getQueueCount()
    {
        return 0;
    }
    
    public function countAll($fresh = null)
    {
        return 0;
    }

    public function countByTag($tag, $fresh = null)
    {
        return 0;
    }

    
    public function remove($key, $force = false)
    {
        return false;
    }
    
    public function removeAll($force = false)
    {
        return false;
    }
    
    public function outdate($key, $force = false)
    {
        return false;
    }
    
    public function outdateAll($force = false)
    {
        return false;
    }

    public function obtainLock($key, $lockFor, $timeout = null)
    {
        return false;
    }

    public function outdateByTag($tag, $force = false)
    {
        return false;
    }
    
    public function clearQueue()
    {
        return false;
    }
    
    public function releaseLock($key, $lockKey)
    {
        return false;
    }

    public function removeByTag($tag, $force = false)
    {
        return false;
    }

    public function getByTag($key, $onlyFresh = false)
    {
        return array();
    }

    public function cleanup($outdatedFor = 0)
    {
        return false;
    }
    
}
