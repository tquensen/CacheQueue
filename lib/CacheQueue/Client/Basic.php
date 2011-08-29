<?php

namespace CacheQueue\Client;
use CacheQueue\Connection\ConnectionInterface;

class Basic implements ClientInterface
{
    private $connection;

    public function __construct(ConnectionInterface $connection, $config = array())
    {
        $this->connection = $connection;
    }

    public function get($key, $onlyFresh = false)
    {
        return $this->connection->getValue($key, $onlyFresh);
    }
    
    public function getEntry($key)
    {
        return $this->connection->get($key);
    }

    public function set($key, $data, $freshFor, $force = false)
    {
        return $this->connection->set($key, $data, $freshFor, $force);
    }

    public function queue($key, $task, $params, $freshFor, $force = false)
    {
        return $this->connection->queue($key, $task, $params, $freshFor, $force);
    }

    public function getOrSet($key, $callback, $params, $freshFor, $force = false)
    {
        $result = $this->connection->get($key);
        if (!$result || !$result['is_fresh'] || $force) {
            $data = call_user_func($callback, $params, $this);
            $this->set($key, $data, $freshFor, $force);
            return $data;
        }
        return empty($result['data']) ? false : $result['data'];
    }

    public function getOrQueue($key, $task, $params, $freshFor, $force = false)
    {
        $result = $this->connection->get($key);
        if (!$result || (!$result['is_fresh'] && !$result['queue_is_fresh']) || $force) {
            $this->queue($key, $task, $params, $freshFor, $force);
        }
        return empty($result['data']) ? false : $result['data'];
    }

    public function outdate($key, $force = false, $persistent = null)
    {
        return $this->connection->outdate($key, $force, $persistent);
    }
    
    public function outdateByTag($tag, $force = false, $persistent = null)
    {
        return $this->connection->outdateByTag($tag, $force, $persistent);
    }

    public function outdateAll($force = false, $persistent = null)
    {
        return $this->connection->outdateAll($force, $persistent);
    }

    public function remove($key, $force = false, $persistent = null)
    {
        return $this->connection->remove($key, $force, $persistent);
    }
    
    public function removeByTag($tag, $force = false, $persistent = null)
    {
        return $this->connection->removeByTag($tag, $force, $persistent);
    }

    public function removeAll($force = false, $persistent = null)
    {
        return $this->connection->removeAll($force, $persistent);
    }
    
    public function setConnection(ConnectionInterface $connection)
    {
         $this->connection = $connection;
    }
    
    public function getConnection()
    {
        return $this->connection;
    }

}
