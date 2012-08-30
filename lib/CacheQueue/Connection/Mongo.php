<?php
namespace CacheQueue\Connection;

class Mongo implements ConnectionInterface
{
    private $dbName = null;
    private $collectionName = null;
    
    private $db = null;
    private $collection = null;
    
    private $safe = null;
    
    public function __construct($config = array())
    {
        $mongo = new \Mongo(!empty($config['server']) ? $config['server'] : 'mongodb://localhost:27017', !empty($config['dboptions']) ? $config['dboptions'] : array());

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
        $this->collection->ensureIndex(array('tags' => 1), array('safe' => true));
        $this->collection->ensureIndex(array('queue_priority' => 1), array('safe' => true));
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

        $return['date_set'] = !empty($result['date_set']) ? $result['date_set']->sec : 0;
        
        $return['queue_fresh_until'] = !empty($result['queue_fresh_until']) ? $result['queue_fresh_until']->sec : 0;
        $return['queue_persistent'] = !empty($result['queue_persistent']);
        $return['queue_is_fresh'] = !empty($result['queue_persistent']) || (!empty($result['queue_fresh_until']) && $result['queue_fresh_until']->sec > time());
        $return['tags'] = isset($result['tags']) ? $result['tags'] : array();
        $return['task'] = !empty($result['task']) ? $result['task'] : null;
        $return['params'] = !empty($result['params']) ? $result['params'] : null;
        $return['data'] = isset($result['data']) ? $result['data'] : false;

        return $return;
    }
    
    public function getByTag($tag, $onlyFresh = false)
    {
        $tags = array_values((array) $tag);
        $return = array();
        
        if ($onlyFresh) {
            $results = $this->collection->find(
                array(
                    'tags' => array('$in' => $tags),
                    '$or' => array(
                        array(
                            'fresh_until' => array('$gte' => new \MongoDate())
                        ),
                        array(
                            'persistent' => true
                        )
                    )
                )
            );
        } else {
            $results = $this->collection->find(
                array(
                    'tags' => array('$in' => $tags)
                )
            );
        }
        
        foreach ($results as $result) {
            $entry = array();
            $entry['key'] = $result['_id'];
            //$entry['queued'] = !empty($result['queued']);
            $entry['fresh_until'] = !empty($result['fresh_until']) ? $result['fresh_until']->sec : 0;
            $entry['persistent'] = !empty($result['persistent']);
            $entry['is_fresh'] = $entry['persistent'] || $entry['fresh_until'] > time();

            $return['date_set'] = !empty($result['date_set']) ? $result['date_set']->sec : 0;
            
            $entry['queue_fresh_until'] = !empty($result['queue_fresh_until']) ? $result['queue_fresh_until']->sec : 0;
            $entry['queue_persistent'] = !empty($result['queue_persistent']);
            $entry['queue_is_fresh'] = !empty($result['queue_persistent']) || (!empty($result['queue_fresh_until']) && $result['queue_fresh_until']->sec > time());
            $entry['tags'] = isset($result['tags']) ? $result['tags'] : array();
            $entry['task'] = !empty($result['task']) ? $result['task'] : null;
            $entry['params'] = !empty($result['params']) ? $result['params'] : null;
            $entry['data'] = isset($result['data']) ? $result['data'] : false;
            $return[] = $entry;
        }
        
        unset($results);
        
        return $return;
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
        $result = $this->db->command(array(
            'findAndModify' => $this->collectionName,
            'query' => array('queued' => true),
            'sort' => array('queue_priority' => 1),
            'update' => array('$set' => array('queued' => $workerId))
        ));
        
        if (empty($result['ok']) || empty($result['value'])) {
            return false;
        }
        
        $return = array();
        
        $return['key'] = $result['value']['_id'];
        $return['fresh_until'] = !empty($result['value']['queue_fresh_until']) ? $result['value']['queue_fresh_until']->sec : 0;
        $return['persistent'] = !empty($result['queue_persistent']);
        $return['tags'] = !empty($result['value']['queue_tags']) ? $result['value']['queue_tags'] : null;
        $return['task'] = !empty($result['value']['task']) ? $result['value']['task'] : null;
        $return['params'] = !empty($result['value']['params']) ? $result['value']['params'] : null;
        $return['data'] = !empty($result['value']['data']) ? $result['value']['data'] : null;
        $return['temp'] = !empty($result['value']['temp']);
        $return['worker_id'] = $workerId;
        
        return $return;
    }
    
    public function updateJobStatus($key, $workerId)
    {
        try {
            return (bool) $this->collection->update(
                    array(
                        '_id' => $key,
                        'queued' => $workerId
                    ),
                    array('$set' => array(
                        'queue_fresh_until' => new \MongoDate(0),
                        'queue_persistent' => false,
                        'queued' => false
                    )),
                    array('safe' => $this->safe)
                );
        }  catch (\MongoCursorException $e) {
            if ($e->getCode() == 11000) {
                return false;
            }
            throw $e;
        }
    }
    
    public function set($key, $data, $freshFor, $force = false, $tags = array())
    {
        if ($freshFor === true) {
            $freshUntil = new \MongoDate(0);
            $persistent = true;
        } else {
            $freshUntil = new \MongoDate(time() + $freshFor);
            $persistent = false;
        }
        
        $tags = array_values((array) $tags);
        
        try {
            if ($force) {
                return (bool) $this->collection->update(
                    array(
                        '_id' => $key
                    ),
                    array('$set' => array(
                        'fresh_until' => $freshUntil,
                        'persistent' => $persistent,
                        'data' => $data,
                        'tags' => $tags,
                        'date_set' => new \MongoDate()
                    )),
                    array('upsert' => true, 'safe' => $this->safe)
                );
            } else {
                return (bool) $this->collection->update(
                    array(
                        '_id' => $key,
                        '$or' => array(
                            array(
                                'fresh_until' => array('$lt' => new \MongoDate()),
                                'persistent' => false
                                ),
                            array('persistent' => null)
                        )
                        
                    ),
                    array('$set' => array(
                        'fresh_until' => $freshUntil,
                        'persistent' => $persistent,
                        'data' => $data,
                        'tags' => $tags,
                        'date_set' => new \MongoDate()
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

    public function queue($key, $task, $params, $freshFor, $force = false, $tags = array(), $priority = 50)
    {
        if ($key === true) {
            $key = 'temp_'.md5(microtime(true).rand(10000,99999));
            $force = true;
            $freshFor = true;
            $temp = true;
        } else {
            $temp = false;
        }
        
        if ($freshFor === true) {
            $freshUntil = new \MongoDate(0);
            $persistent = true;
        } else {
            $freshUntil = new \MongoDate(time() + $freshFor);
            $persistent = false;
        }
        
        $tags = array_values((array) $tags);
        
        try {
            if ($force) {
                return (bool) $this->collection->update(
                    array(
                        '_id' => $key
                    ),
                    array('$set' => array(
                        'queue_fresh_until' => $freshUntil,
                        'queue_persistent' => $persistent,
                        'queue_tags' => $tags,
                        'queued' => true,
                        'task' => $task,
                        'params' => $params,
                        'temp' => $temp,
                        'queue_priority' => $priority
                    )),
                    array('upsert' => true, 'safe' => $this->safe)
                );
            } else {
                return (bool) $this->collection->update(
                    array(
                        '_id' => $key,
                        '$or' => array(
                            array(
                                'fresh_until' => array('$lt' => new \MongoDate()),
                                'queue_fresh_until' => array('$lt' => new \MongoDate()),
                                'persistent' => false,
                                'queue_persistent' => false
                                ),
                            array(
                                'fresh_until' => array('$lt' => new \MongoDate()),
                                'persistent' => false,
                                'queue_persistent' => null
                                ),
                            array(
                                'queue_fresh_until' => array('$lt' => new \MongoDate()),
                                'queue_persistent' => false,
                                'persistent' => null
                                ),
                            array(
                                'persistent' => null,
                                'queue_persistent' => null
                                ),
                        )
                    ),
                    array('$set' => array(
                        'queue_fresh_until' => $freshUntil,
                        'queue_persistent' => $persistent,
                        'queue_tags' => $tags,
                        'queued' => true,
                        'task' => $task,
                        'params' => $params,
                        'temp' => $temp,
                        'queue_priority' => $priority
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
                        '$or' => array(
                            array(
                                'fresh_until' => array('$lt' => new \MongoDate()),
                                'persistent' => false
                                ),
                            array('persistent' => null)
                        )
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
    
    public function removeByTag($tag, $force = false, $persistent = null)
    {
        $tags = array_values((array) $tag);
        if (!$force) {
            return (bool) $this->collection->remove(
                    array(
                        '$or' => array(
                            array(
                                'fresh_until' => array('$lt' => new \MongoDate()),
                                'persistent' => false
                                ),
                            array('persistent' => null)
                        ),
                        'tags' => array('$in' => $tags)
                    ),
                    array('safe' => $this->safe, 'multiple' => true)
                );
        } else {
            if ($persistent !== null) {
                return (bool) $this->collection->remove(
                    array(
                        'persistent' => (bool) $persistent,
                        'tags' => array('$in' => $tags)
                    ),
                    array('safe' => $this->safe, 'multiple' => true)
                );
            } else {
                return (bool) $this->collection->remove(
                    array('tags' => array('$in' => $tags)),
                    array('safe' => $this->safe, 'multiple' => true)
                );
            }

        }
    }
    
    public function removeAll($force = false, $persistent = null)
    {
        if (!$force) {
            return (bool) $this->collection->remove(
                    array(
                        '$or' => array(
                            array(
                                'fresh_until' => array('$lt' => new \MongoDate()),
                                'persistent' => false
                                ),
                            array('persistent' => null)
                        )
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
                        'persistent' => false,
                    ),
                    array('$set' => array(
                        'fresh_until' => new \MongoDate(time() - 1),
                        'persistent' => false,
                        'queue_fresh_until' => new \MongoDate(time() - 1),
                        'queue_persistent' => false,
                        'queued' => false
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
                        'persistent' => false,
                        'queue_fresh_until' => new \MongoDate(time() - 1),
                        'queue_persistent' => false,
                        'queued' => false
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
                        'persistent' => false,
                        'queue_fresh_until' => new \MongoDate(time() - 1),
                        'queue_persistent' => false,
                        'queued' => false
                    )),
                    array('safe' => $this->safe)
                );
            }

        }
    }
    
    public function outdateByTag($tag, $force = false, $persistent = null)
    {
        $tags = array_values((array) $tag);
        if (!$force) {
            return (bool) $this->collection->update(
                    array(
                        'fresh_until' => array('$gt' => new \MongoDate()),
                        'persistent' => false,
                        'tags' => array('$in' => $tags)
                    ),
                    array('$set' => array(
                        'fresh_until' => new \MongoDate(time() - 1),
                        'persistent' => false,
                        'queue_fresh_until' => new \MongoDate(time() - 1),
                        'queue_persistent' => false,
                        'queued' => false
                    )),
                    array('safe' => $this->safe, 'multiple' => true)
                );
        } else {
            if ($persistent !== null) {
                return (bool) $this->collection->update(
                    array(
                        'persistent' => (bool) $persistent,
                        'tags' => array('$in' => $tags)
                    ),
                    array('$set' => array(
                        'fresh_until' => new \MongoDate(time() - 1),
                        'persistent' => false,
                        'queue_fresh_until' => new \MongoDate(time() - 1),
                        'queue_persistent' => false,
                        'queued' => false
                    )),
                    array('safe' => $this->safe, 'multiple' => true)
                );
            } else {
                return (bool) $this->collection->update(
                    array(
                        'tags' => array('$in' => $tags)
                    ),
                    array('$set' => array(
                        'fresh_until' => new \MongoDate(time() - 1),
                        'persistent' => false,
                        'queue_fresh_until' => new \MongoDate(time() - 1),
                        'queue_persistent' => false,
                        'queued' => false
                    )),
                    array('safe' => $this->safe, 'multiple' => true)
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
                        'fresh_until' => new \MongoDate(time() - 1),
                        'persistent' => false,
                        'queue_fresh_until' => new \MongoDate(time() - 1),
                        'queue_persistent' => false,
                        'queued' => false
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
                        'persistent' => false,
                        'queue_fresh_until' => new \MongoDate(time() - 1),
                        'queue_persistent' => false,
                        'queued' => false
                    )),
                    array('safe' => $this->safe, 'multiple' => true)
                );
            } else {
                return (bool) $this->collection->update(
                    array(
                    ),
                    array('$set' => array(
                        'fresh_until' => new \MongoDate(time() - 1),
                        'persistent' => false,
                        'queue_fresh_until' => new \MongoDate(time() - 1),
                        'queue_persistent' => false,
                        'queued' => false
                    )),
                    array('safe' => $this->safe, 'multiple' => true)
                );
            }

        }
    }

    public function obtainLock($key, $lockFor, $timeout = null)
    {
        $waitUntil = microtime(true) + ($timeout !== null ? (float) $timeout : (float) $lockFor);
        $lockKey = md5(microtime().rand(100000,999999));
        do {
            $this->set($key.'._lock', $lockKey, $lockFor);
            $data = $this->get($key.'._lock');
            if ($data && $data['data'] == $lockKey) {
                return $lockKey;
            } elseif ($data && !$data['is_fresh']) {
                $this->releaseLock($key, $data['data']);
            } else {
                usleep(50000);
            }
        } while(microtime(true) < $waitUntil);
        return false;
    }

    public function releaseLock($key, $lockKey)
    {
        if ($lockKey === true) {
            return $this->remove($key.'._lock', true);
        }
        $this->collection->remove(
            array(
                '_id' => $key.'._lock',
                'data' => $lockKey
            ),
            array('safe' => $this->safe)
        );
        return true;
    }
    
}
