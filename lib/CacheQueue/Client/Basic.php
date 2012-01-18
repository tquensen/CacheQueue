<?php

namespace CacheQueue\Client;
use CacheQueue\Connection\ConnectionInterface,
    CacheQueue\Worker\WorkerInterface,
    CacheQueue\Exception\Exception;

class Basic implements ClientInterface
{
    private $connection;
    private $worker;

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

    public function queue($key, $task, $params, $freshFor, $force = false, $tags = array())
    {
        return $this->connection->queue($key, $task, $params, $freshFor, $force, $tags);
    }
    
    public function queueTemporary($task, $params, $tags = array())
    {
        return $this->connection->queue(true, $task, $params, true, true, $tags);
    }

    public function getOrSet($key, $callback, $params, $freshFor, $force = false, $tags = array())
    {
        $result = $this->connection->get($key);
        if (!$result || !$result['is_fresh'] || $force) {
            $data = call_user_func($callback, $params, $this);
            $this->set($key, $data, $freshFor, $force, $tags);
            return $data;
        }
        return empty($result['data']) ? false : $result['data'];
    }

    public function getOrQueue($key, $task, $params, $freshFor, $force = false, $tags = array())
    {
        $result = $this->connection->get($key);
        if (!$result || (!$result['is_fresh'] && !$result['queue_is_fresh']) || $force) {
            $this->queue($key, $task, $params, $freshFor, $force, $tags);
        }
        return empty($result['data']) ? false : $result['data'];
    }
    
    public function getOrRun($key, $task, $params, $freshFor, $force = false, $tags = array())
    {
        $result = $this->connection->get($key);
        if (!$result || (!$result['is_fresh']) || $force) {
            if (!$worker = $this->getWorker()) {
                throw new Exception('no worker found');
            }

            $job = array(
                'key' => $key,
                'fresh_until' => $freshFor === true ? 0 : time()-$freshFor,
                'persistent' => $freshFor === true,
                'tags' => $tags,
                'task' => $task,
                'params' => $params,
                'data' => !empty($result['data']) ? $result['data'] : null,
                'temp' => false
            );
            $data = $worker->work($job);
            return empty($data) ? false : $data;
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

    public function getWorker()
    {
        return $this->worker;
    }

    public function setWorker(WorkerInterface $worker)
    {
        $this->worker = $worker;
    }

}
