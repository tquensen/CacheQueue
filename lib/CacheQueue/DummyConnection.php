<?php
namespace CacheQueue;

class DummyConnection implements IConnection
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

    public function getJob()
    {
        return false;
    }
    
    public function set($key, $data, $freshFor, $force = false)
    {
        return false;
    }

    public function queue($key, $task, $params, $freshFor, $force = false)
    {
        return false;
    }

    public function setData($key, $data)
    {
        return false;
    }

    public function getQueueCount()
    {
        return 0;
    }
    
    public function remove($key, $force = false, $persistent = null)
    {
        return false;
    }
    
    public function removeAll($force = false, $persistent = null)
    {
        return false;
    }
    
    public function outdate($key, $force = false, $persistent = null)
    {
        return false;
    }
    
    public function outdateAll($force = false, $persistent = null)
    {
        return false;
    }
    
}
