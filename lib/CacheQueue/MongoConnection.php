<?php
namespace CacheQueue;

class MongoConnection implements IConnection
{
    private $dbName = null;
    private $collectionName = null;
    
    private $db = null;
    private $collection = null;
    
    private $safe = null;
    
    public function __construct($config = array())
    {
        if (!empty($config['server'])) {
            $mongo = new \Mongo($config['server'], !empty($config['dboptions']) ? $config['dboptions'] : array());
        } else {
            $mongo = new \Mongo();
        }
        $this->dbName = !empty($config['database']) ? $config['database'] : 'cachequeue';
        $this->collectionName = !empty($config['collection']) ? $config['collection'] : 'cache';
        
        $this->db = $mongo->{$this->dbName};
        $this->collection = $this->db->{$this->collectionName};
        
        $this->safe = !empty($config['safe']) ? true : false;
    }
    
    public function setup()
    {
        $this->collection->ensureIndex(array('queued' => 1), array('safe' => true));
        $this->collection->ensureIndex(array('fresh_until' => 1), array('safe' => true));
        $this->collection->ensureIndex(array('queue_fresh_until' => 1), array('safe' => true));
        $this->collection->ensureIndex(array('persistent' => 1), array('safe' => true));
        $this->collection->ensureIndex(array('queue_persistent' => 1), array('safe' => true));
    }

    public function get($key)
    {
        $result = $this->collection->findOne(array('_id' => $key));
        if (!$result) {
            return false;
        }
        $return = array();
        
        $return['key'] = $result['_id'];
        //$return['queued'] = !empty($result['queued']);
        $return['fresh_until'] = !empty($result['fresh_until']) ? $result['fresh_until']->sec : 0;
        $return['persistent'] = !empty($result['persistent']);
        $return['is_fresh'] = $return['persistent'] || $return['fresh_until'] > time();

        $return['queue_is_fresh'] = !empty($result['queue_persistent']) || (!empty($result['queue_fresh_until']) && $result['queue_fresh_until'] > time());

        //$return['task'] = !empty($result['task']) ? $result['task'] : null;
        //$return['params'] = !empty($result['params']) ? $result['params'] : null;
        $return['data'] = isset($result['data']) ? $result['data'] : false;

        return $return;
    }

    public function getJob()
    {
        $result = $this->db->command(array(
            'findAndModify' => $this->collectionName,
            'query' => array('queued' => true),
            'update' => array('$set' => array('queued' => false))
        ));
        
        if (empty($result['ok']) || empty($result['value'])) {
            return false;
        }
        
        $return = array();
        
        $return['key'] = $result['value']['_id'];
        $return['fresh_until'] = !empty($result['value']['queue_fresh_until']) ? $result['value']['queue_fresh_until']->sec : 0;
        $return['persistent'] = !empty($result['queue_persistent']);
        $return['task'] = !empty($result['value']['task']) ? $result['value']['task'] : null;
        $return['params'] = !empty($result['value']['params']) ? $result['value']['params'] : null;
        $return['data'] = !empty($result['value']['data']) ? $result['value']['data'] : null;
        
        return $return;
    }
    
    public function set($key, $data, $freshFor, $force = false)
    {
        if ($freshFor === true) {
            $freshUntil = new \MongoDate(0);
            $persistent = true;
        } else {
            $freshUntil = new \MongoDate(time() + $freshFor);
            $persistent = false;
        }
        try {
            if ($force) {
                return (bool) $this->collection->update(
                    array(
                        '_id' => $key
                    ),
                    array('$set' => array(
                        '_id' => $key,
                        'fresh_until' => $freshUntil,
                        'persistent' => $persistent,
                        'data' => $data
                    )),
                    array('upsert' => true, 'safe' => $this->safe)
                );
            } else {
                return (bool) $this->collection->update(
                    array(
                        '_id' => $key,
                        'fresh_until' => array('$lt' => new \MongoDate()),
                        'persistent' => false
                    ),
                    array('$set' => array(
                        '_id' => $key,
                        'fresh_until' => $freshUntil,
                        'persistent' => $persistent,
                        'data' => $data
                    )),
                    array('upsert' => true, 'safe' => $this->safe)
                );
            }
        } catch (\MongoCursorException $e) {
            if ($e->getCode() == 11000) {
                return true;
            }
            throw $e;
        }
        
    }

    public function queue($key, $task, $params, $freshFor, $force = false)
    {
        if ($freshFor === true) {
            $freshUntil = true;
            $persistent = true;
        } else {
            $freshUntil = new \MongoDate(time() + $freshFor);
            $persistent = false;
        }
        try {
            if ($force) {
                return (bool) $this->collection->update(
                    array(
                        '_id' => $key
                    ),
                    array('$set' => array(
                        'queue_fresh_until' => $freshUntil,
                        'queue_persistent' => $persistent,
                        'queued' => true,
                        'task' => $task,
                        'params' => $params
                    )),
                    array('upsert' => true, 'safe' => $this->safe)
                );
            } else {
                return (bool) $this->collection->update(
                    array(
                        '_id' => $key,
                        'fresh_until' => array('$lt' => new \MongoDate()),
                        'queue_fresh_until' => array('$lt' => new \MongoDate()),
                        'persistent' => false,
                        'queue_persistent' => false
                    ),
                    array('$set' => array(
                        'queue_fresh_until' => $freshUntil,
                        'queue_persistent' => $persistent,
                        'queued' => true,
                        'task' => $task,
                        'params' => $params
                    )),
                    array('upsert' => true, 'safe' => $this->safe)
                );
            }
        }  catch (\MongoCursorException $e) {
            if ($e->getCode() == 11000) {
                return true;
            }
            throw $e;
        }
    }

    public function getQueueCount()
    {
        return $this->collection->count(array('queued' => true));
    }
    
    public function remove($key, $force = false, $persistent = null)
    {
        if (!$force) {
            return (bool) $this->collection->remove(
                    array(
                        '_id' => $key,
                        'fresh_until' => array('$lt' => new \MongoDate())
                    ),
                    array('safe' => $this->safe)
                );
        } else {
            if ($persistent !== null) {
                return (bool) $this->collection->remove(
                    array(
                        '_id' => $key,
                        'persistent' => (bool) $persistent
                    ),
                    array('safe' => $this->safe)
                );
            } else {
                return (bool) $this->collection->remove(
                    array(
                        '_id' => $key
                    ),
                    array('safe' => $this->safe)
                );
            }

        }
    }
    
    public function removeAll($force = false, $persistent = null)
    {
        if (!$force) {
            return (bool) $this->collection->remove(
                    array(
                        'fresh_until' => array('$lt' => new \MongoDate())
                    ),
                    array('safe' => $this->safe, 'multiple' => true)
                );
        } else {
            if ($persistent !== null) {
                return (bool) $this->collection->remove(
                    array(
                        'persistent' => (bool) $persistent
                    ),
                    array('safe' => $this->safe, 'multiple' => true)
                );
            } else {
                return (bool) $this->collection->remove(
                    array(),
                    array('safe' => $this->safe, 'multiple' => true)
                );
            }

        }
    }
    
    public function outdate($key, $force = false, $persistent = null)
    {
        if (!$force) {
            return (bool) $this->collection->update(
                    array(
                        '_id' => $key,
                        'fresh_until' => array('$gt' => new \MongoDate()),
                        'persistent' => false
                    ),
                    array('$set' => array(
                        'fresh_until' => new \MongoDate(time() - 1)
                    )),
                    array('safe' => $this->safe)
                );
        } else {
            if ($persistent !== null) {
                return (bool) $this->collection->update(
                    array(
                        '_id' => $key,
                        'persistent' => (bool) $persistent
                    ),
                    array('$set' => array(
                        'fresh_until' => new \MongoDate(time() - 1),
                        'persistent' => false
                    )),
                    array('safe' => $this->safe)
                );
            } else {
                return (bool) $this->collection->update(
                    array(
                        '_id' => $key,
                    ),
                    array('$set' => array(
                        'fresh_until' => new \MongoDate(time() - 1),
                        'persistent' => false
                    )),
                    array('safe' => $this->safe)
                );
            }

        }
    }
    
    public function outdateAll($force = false, $persistent = null)
    {
        if (!$force) {
            return (bool) $this->collection->update(
                    array(
                        'fresh_until' => array('$gt' => new \MongoDate()),
                        'persistent' => false
                    ),
                    array('$set' => array(
                        'fresh_until' => new \MongoDate(time() - 1)
                    )),
                    array('safe' => $this->safe, 'multiple' => true)
                );
        } else {
            if ($persistent !== null) {
                return (bool) $this->collection->update(
                    array(
                        'persistent' => (bool) $persistent
                    ),
                    array('$set' => array(
                        'fresh_until' => new \MongoDate(time() - 1),
                        'persistent' => false
                    )),
                    array('safe' => $this->safe, 'multiple' => true)
                );
            } else {
                return (bool) $this->collection->update(
                    array(
                    ),
                    array('$set' => array(
                        'fresh_until' => new \MongoDate(time() - 1),
                        'persistent' => false
                    )),
                    array('safe' => $this->safe, 'multiple' => true)
                );
            }

        }
    }
    
}
