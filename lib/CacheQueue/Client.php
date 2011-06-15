<?php

namespace CacheQueue;

class Client implements IClient
{
    private $connection;

    public function __construct(IConnection $connection, $config = array())
    {
        $this->connection = $connection;
    }

    public function get($key, $onlyValue = true)
    {
        $result = $this->connection->get($key);
        if ($onlyValue === false) {
            return $result;
        }
        if (!$result || empty($result['data'])) {
            return false;
        }
        return $result['data'];
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
        if (!$result || !$result['is_fresh'] || $force) {
            $this->queue($key, $task, $params, $freshFor, $force);
        }
        return empty($result['data']) ? false : $result['data'];
    }

    public function outdate($key, $force = false, $persistent = null)
    {
        return $this->connection->outdate($key, $force, $persistent);
    }

    public function outdateAll($force = false, $persistent = null)
    {
        return $this->connection->outdateAll($force, $persistent);
    }

    public function remove($key, $force = false, $persistent = null)
    {
        return $this->connection->remove($key, $force, $persistent);
    }

    public function removeAll($force = false, $persistent = null)
    {
        return $this->connection->removeAll($force, $persistent);
    }
    
    public function setConnection(IConnection $connection)
    {
         $this->connection = $connection;
    }
    
    public function getConnection()
    {
        return $this->connection;
    }

}
