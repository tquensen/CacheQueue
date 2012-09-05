<?php
namespace CacheQueue\Connection;

class APCProxy implements ConnectionInterface
{
    private $prefix = null;
    /**
     *
     * @var ConnectionInterface
     */
    private $connection = null;
    
    public function __construct($config = array())
    {
        $this->prefix = !empty($config['prefix']) ? $config['prefix'] : 'cc_';
        
        $connectionClass = $config['connectionClass'];
        if (!class_exists($connectionClass)) {
            $connectionFile = !empty($config['connectionFile']) ? $config['connectionFile'] : str_replace('\\', \DIRECTORY_SEPARATOR, trim($connectionClass, '\\')).'.php';
            require_once($connectionFile);
        }
        $this->connection = new $connectionClass($config['connectionConfig']);
    }
    
    public function setup()
    {
        if (method_exists($this->connection, 'setup')) {
            return $this->connection->setup();
        }
        return false;
    }

    public function get($key)
    {
        if (!$result = apc_fetch($this->prefix.$key)) {
            $result = $this->connection->get($key);
            if ($result && $result['is_fresh']) {
                apc_store($this->prefix.$key, $result, $result['persistent'] ? 0 : $result['fresh_until'] - time() + 1);
            }
        }
        return $result;
    }
    
    public function getByTag($tag, $onlyFresh = false)
    {
        return $this->connection->getByTag($tag, $onlyFresh);
    }
    
    public function getValue($key, $onlyFresh = false)
    {
        $result = $this->get($key);
        if (!$result || empty($result['data'])) {
            return false;
        }
        return (!$onlyFresh || $result['is_fresh']) ? $result['data'] : false;
    }

    public function getJob($workerId)
    {
        return $this->connection->getJob($workerId);
    }
    
    public function updateJobStatus($key, $workerId)
    {
        if (apc_exists($this->prefix.$key)) apc_delete($this->prefix.$key);
        return $this->connection->updateJobStatus($key, $workerId);
    }
    
    public function set($key, $data, $freshFor, $force = false, $tags = array())
    {
        if (apc_exists($this->prefix.$key)) apc_delete($this->prefix.$key);
        return $this->connection->set($key, $data, $freshFor, $force, $tags);
    }

    public function queue($key, $task, $params, $freshFor, $force = false, $tags = array(), $priority = 50)
    {
        if (apc_exists($this->prefix.$key)) apc_delete($this->prefix.$key);
        return $this->connection->queue($key, $task, $params, $freshFor, $force, $tags, $priority);
    }

    public function getQueueCount()
    {
        return $this->connection->getQueueCount();
    }
    
    public function remove($key, $force = false, $persistent = null)
    {
        if (apc_exists($this->prefix.$key)) apc_delete($this->prefix.$key);
        return $this->connection->remove($key, $force, $persistent);
    }
    
    public function removeByTag($tag, $force = false, $persistent = null)
    {
        if ($force) {
            apc_delete(new \APCIterator('user', '/^'.$this->prefix.'/'));
        }
        return $this->connection->removeByTag($tag, $force, $persistent);
    }
    
    public function removeAll($force = false, $persistent = null)
    {
        if ($force) {
            apc_delete(new \APCIterator('user', '/^'.$this->prefix.'/'));
        }
        return $this->connection->removeAll($force, $persistent);
    }
    
    public function outdate($key, $force = false, $persistent = null)
    {
        if (apc_exists($this->prefix.$key)) apc_delete($this->prefix.$key);
        return $this->connection->outdate($key, $force, $persistent);
    }
    
    public function outdateByTag($tag, $force = false, $persistent = null)
    {
        if ($force) {
            apc_delete(new \APCIterator('user', '/^'.$this->prefix.'/'));
        }
        return $this->connection->outdateByTag($tag, $force, $persistent);
    }
    
    public function outdateAll($force = false, $persistent = null)
    {
        if ($force) {
            apc_delete(new \APCIterator('user', '/^'.$this->prefix.'/'));
        }
        return $this->connection->outdateAll($force, $persistent);
    }

    public function obtainLock($key, $lockFor, $timeout = null)
    {
        return $this->connection->obtainLock($key, $lockFor, $timeout);
    }

    public function releaseLock($key, $lockKey)
    {
        return $this->connection->releaseLock($key, $lockKey);
    }
    
}
