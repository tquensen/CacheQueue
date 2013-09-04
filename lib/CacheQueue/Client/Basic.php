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

    public function set($key, $data, $freshFor, $force = false, $tags = array())
    {
        return $this->connection->set($key, $data, $freshFor, $force, $tags);
    }

    public function queue($key, $task, $params, $freshFor, $force = false, $tags = array(), $priority = 50)
    {
        return $this->connection->queue($key, $task, $params, $freshFor, $force, $tags, $priority);
    }
    
    public function queueTemporary($task, $params, $priority = 50)
    {
        return $this->connection->queue(true, $task, $params, true, true, array(), $priority);
    }
    
    public function run($task, $params)
    {

        if (!$worker = $this->getWorker()) {
            throw new Exception('no worker found');
        }

        $job = array(
            'key' => false,
            'fresh_until' => 0,
            'tags' => array(),
            'task' => $task,
            'params' => $params,
            'data' => null,
            'temp' => true,
            'worker_id' => $worker->getWorkerId()
        );

        $data = $worker->executeTask($task, $params, $job);
        return empty($data) ? false : $data;
    }

    public function getOrSet($key, $callback, $params, $freshFor, $force = false, $tags = array(), $lockFor = false, $lockTimeout = false)
    {
        $result = $this->connection->get($key);
        if (!$result || !$result['is_fresh'] || $force) {
            if ($lockFor === false) {
                $data = call_user_func($callback, $params, $this, $result);
                $this->set($key, $data, $freshFor, $force, $tags);
                return $data;
            } else {
                $lockKey = $this->connection->obtainLock($key, $lockFor, $lockTimeout !== false ? $lockTimeout : $lockFor);
                if ($lockKey) {
                    $result = $this->connection->get($key);
                    if (!$result || !$result['is_fresh']) {
                        try {
                            $data = call_user_func($callback, $params, $this, $result);
                            $this->set($key, $data, $freshFor, $force, $tags);
                            $this->connection->releaseLock($key, $lockKey);
                            return $data;
                        } catch (\Exception $e) {
                            $this->connection->releaseLock($key, $lockKey);
                            throw $e;
                        }
                    }
                    $this->connection->releaseLock($key, $lockKey);
                } else {
                    return false;
                }
            }
        }
        return !isset($result['data']) ? false : $result['data'];
    }

    public function getOrQueue($key, $task, $params, $freshFor, $force = false, $tags = array())
    {
        $result = $this->connection->get($key);
        if (!$result || (!$result['is_fresh'] && !$result['queue_is_fresh']) || $force) {
            $this->queue($key, $task, $params, $freshFor, $force, $tags);
        }
        return !isset($result['data']) ? false : $result['data'];
    }
    
    public function getOrRun($key, $task, $params, $freshFor, $force = false, $tags = array(), $lockFor = false, $lockTimeout = false)
    {
        $result = $this->connection->get($key);
        if (!$result || (!$result['is_fresh']) || $force) {
            if (!$worker = $this->getWorker()) {
                throw new Exception('no worker found');
            }

            $job = array(
                'key' => $key,
                'fresh_until' => time() + $freshFor,
                'tags' => $tags,
                'task' => $task,
                'params' => $params,
                'data' => isset($result['data']) ? $result['data'] : null,
                'temp' => false,
                'worker_id' => $worker->getWorkerId()
            );
            
            if ($lockFor === false) {
                $data = $worker->work($job);
                return !isset($data) ? false : $data;
            } else {
                $lockKey = $this->connection->obtainLock($key, $lockFor, $lockTimeout !== false ? $lockTimeout : $lockFor);
                if ($lockKey) {
                    $result = $this->connection->get($key);
                    if (!$result || !$result['is_fresh']) {
                        try {
                            $data = $worker->work($job);
                            $this->connection->releaseLock($key, $lockKey);
                            return $data;
                        } catch (\Exception $e) {
                            $this->connection->releaseLock($key, $lockKey);
                            throw $e;
                        }
                    }
                    $this->connection->releaseLock($key, $lockKey);
                } else {
                    return false;
                }
            }
        }
        return !isset($result['data']) ? false : $result['data'];
    }

    public function outdate($key, $force = false)
    {
        return $this->connection->outdate($key, $force);
    }
    
    public function outdateByTag($tag, $force = false)
    {
        return $this->connection->outdateByTag($tag, $force);
    }

    public function outdateAll($force = false)
    {
        return $this->connection->outdateAll($force);
    }

    public function remove($key, $force = false)
    {
        return $this->connection->remove($key, $force);
    }
    
    public function removeByTag($tag, $force = false)
    {
        return $this->connection->removeByTag($tag, $force);
    }

    public function removeAll($force = false)
    {
        return $this->connection->removeAll($force);
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
