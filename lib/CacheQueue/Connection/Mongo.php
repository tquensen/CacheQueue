<?php
namespace CacheQueue\Connection;

class Mongo implements ConnectionInterface
{
    private $dbName = null;
    private $collectionName = null;
    
    private $db = null;
    private $collection = null;
    
    private $w = null;
    
    public function __construct($config = array())
    {
        $mongo = new \MongoClient(!empty($config['server']) ? $config['server'] : 'mongodb://localhost:27017', !empty($config['dboptions']) ? $config['dboptions'] : array());

        $this->dbName = !empty($config['database']) ? $config['database'] : 'cachequeue';
        $this->collectionName = !empty($config['collection']) ? $config['collection'] : 'cache';
        
        $this->db = $mongo->{$this->dbName};
        $this->collection = $this->db->{$this->collectionName};
        
        $this->w = isset($config['w']) ? $config['w'] : 1;
    }
    
    public function setup()
    {
        $this->collection->ensureIndex(array('queued' => 1, 'queue_priority' => 1), array('w' => $this->w));

        $this->collection->ensureIndex(array('fresh_until' => 1, 'tags' => 1), array('w' => $this->w));
        $this->collection->ensureIndex(array('persistent' => 1, 'tags' => 1), array('w' => $this->w));
        $this->collection->ensureIndex(array('persistent' => 1, 'fresh_until' => 1, 'tags' => 1), array('w' => $this->w));

        $this->collection->ensureIndex(array('tags' => 1), array('w' => $this->w));
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
                    '$or' => array(
                        array(
                            'fresh_until' => array('$gte' => new \MongoDate()),
                            'tags' => array('$in' => $tags)
                        ),
                        array(
                            'persistent' => true,
                            'tags' => array('$in' => $tags)
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
        if (!$result || !isset($result['data'])) {
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
        $return['persistent'] = !empty($result['value']['queue_persistent']);
        $return['tags'] = !empty($result['value']['queue_tags']) ? $result['value']['queue_tags'] : null;
        $return['task'] = !empty($result['value']['task']) ? $result['value']['task'] : null;
        $return['params'] = !empty($result['value']['params']) ? $result['value']['params'] : null;
        $return['data'] = isset($result['value']['data']) ? $result['value']['data'] : null;
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
                    array('w' => $this->w)
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
                    array('upsert' => true, 'w' => $this->w)
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
                    array('upsert' => true, 'w' => $this->w)
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
                    array('upsert' => true, 'w' => $this->w)
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
                    array('upsert' => true, 'w' => $this->w)
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
    
    public function countAll($fresh = null, $persistent = null)
    {
        if ($fresh === null) {
            if ($persistent !== null) {
                return (int) $this->collection->count(
                    array(
                        'persistent' => $persistent,
                    )
                );
            } else {
                //or is slow with count, and as these queries are XOR, simple addition will do the jobs
                /*
                return (int) $this->collection->count(
                    array(
                        '$or' => array(
                            array(
                                'persistent' => false
                            ),
                            array(
                                'persistent' => true
                            )
                        )
                    )
                );
                */
                return (int) $this->collection->count(array('persistent' => false)) + (int) $this->collection->count(array('persistent' => true));
            }
        } else {
            if ($persistent === false) {
                if ($fresh) {
                    return (int) $this->collection->count(
                        array(
                            'persistent' => false,
                            'fresh_until' => array('$gte' => new \MongoDate())
                        )
                    );
                } else {
                    return (int) $this->collection->count(
                        array(
                            'persistent' => false,
                            'fresh_until' => array('$lt' => new \MongoDate())
                        )
                    );
                }
            } elseif($persistent === true) {
                if ($fresh) {
                    return (int) $this->collection->count(
                        array(
                            'persistent' => true
                        )
                    );
                } else {
                    return 0;
                }
            } else {
                if ($fresh) {
                    /*
                    return (int) $this->collection->count(
                        array(
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
                     */
                    return (int) $this->collection->count(array('persistent' => false, 'fresh_until' => array('$gte' => new \MongoDate()))) + (int) $this->collection->count(array('persistent' => true));
                } else {
                    return (int) $this->collection->count(
                        array(
                            'persistent' => false,
                            'fresh_until' => array('$lt' => new \MongoDate())
                        )
                    );
                }
                
            }

        }
    }
    
    public function countByTag($tag, $fresh = null, $persistent = null)
    {
        $tags = array_values((array) $tag);
        if ($fresh === null) {
            if ($persistent !== null) {
                return (int) $this->collection->count(
                    array(
                        'persistent' => $persistent,
                        'tags' => array('$in' => $tags)
                    )
                );
            } else {
                /*
                return (int) $this->collection->count(
                    array(
                        '$or' => array(
                            array(
                                'persistent' => false,
                                'tags' => array('$in' => $tags)
                            ),
                            array(
                                'persistent' => true,
                                'tags' => array('$in' => $tags)
                            )
                        )
                    )
                );
                 */
                return (int) $this->collection->count(array('persistent' => false, 'tags' => array('$in' => $tags))) + (int) $this->collection->count(array('persistent' => true, 'tags' => array('$in' => $tags)));
            }
        } else {
            if ($persistent === false) {
                if ($fresh) {
                    return (int) $this->collection->count(
                        array(
                            'persistent' => false,
                            'fresh_until' => array('$gte' => new \MongoDate()),
                            'tags' => array('$in' => $tags)
                        )
                    );
                } else {
                    return (int) $this->collection->count(
                        array(
                            'persistent' => false,
                            'fresh_until' => array('$lt' => new \MongoDate()),
                            'tags' => array('$in' => $tags)   
                        )
                    );
                }
            } elseif($persistent === true) {
                if ($fresh) {
                    return (int) $this->collection->count(
                        array(
                            'persistent' => true,
                            'tags' => array('$in' => $tags)
                        )
                    );
                } else {
                    return 0;
                }
            } else {
                if ($fresh) {
                    /*
                    return (int) $this->collection->count(
                        array(
                            '$or' => array(
                                array(
                                    'fresh_until' => array('$gte' => new \MongoDate()),
                                    'tags' => array('$in' => $tags)
                                ),
                                array(
                                    'persistent' => true,
                                    'tags' => array('$in' => $tags)
                                )
                            )
                        )
                    );
                     */
                    return (int) $this->collection->count(array('persistent' => false, 'fresh_until' => array('$gte' => new \MongoDate()), 'tags' => array('$in' => $tags))) + (int) $this->collection->count(array('persistent' => true, 'tags' => array('$in' => $tags)));
                } else {
                    return (int) $this->collection->count(
                        array(
                            'persistent' => false,
                            'fresh_until' => array('$lt' => new \MongoDate()),
                            'tags' => array('$in' => $tags)
                        )
                    );
                }
                
            }

        }
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
                    array('w' => $this->w)
                );
        } else {
            if ($persistent !== null) {
                return (bool) $this->collection->remove(
                    array(
                        '_id' => $key,
                        'persistent' => (bool) $persistent
                    ),
                    array('w' => $this->w)
                );
            } else {
                return (bool) $this->collection->remove(
                    array(
                        '_id' => $key
                    ),
                    array('w' => $this->w)
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
                                'persistent' => false,
                                'fresh_until' => array('$lt' => new \MongoDate()),
                                'tags' => array('$in' => $tags)
                                ),
                            array(
                                'persistent' => null,
                                'tags' => array('$in' => $tags)
                                )
                        )
                    ),
                    array('w' => $this->w, 'multiple' => true)
                );
        } else {
            if ($persistent !== null) {
                return (bool) $this->collection->remove(
                    array(
                        'persistent' => (bool) $persistent,
                        'tags' => array('$in' => $tags)
                    ),
                    array('w' => $this->w, 'multiple' => true)
                );
            } else {
                return (bool) $this->collection->remove(
                    array('tags' => array('$in' => $tags)),
                    array('w' => $this->w, 'multiple' => true)
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
                                'persistent' => false,
                                'fresh_until' => array('$lt' => new \MongoDate())
                                ),
                            array('persistent' => null)
                        )
                    ),
                    array('w' => $this->w, 'multiple' => true)
                );
        } else {
            if ($persistent !== null) {
                return (bool) $this->collection->remove(
                    array(
                        'persistent' => (bool) $persistent
                    ),
                    array('w' => $this->w, 'multiple' => true)
                );
            } else {
                return (bool) $this->collection->remove(
                    array(),
                    array('w' => $this->w, 'multiple' => true)
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
                    array('w' => $this->w)
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
                    array('w' => $this->w)
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
                    array('w' => $this->w)
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
                        'persistent' => false,
                        'fresh_until' => array('$gt' => new \MongoDate()),
                        'tags' => array('$in' => $tags)
                    ),
                    array('$set' => array(
                        'fresh_until' => new \MongoDate(time() - 1),
                        'persistent' => false,
                        'queue_fresh_until' => new \MongoDate(time() - 1),
                        'queue_persistent' => false,
                        'queued' => false
                    )),
                    array('w' => $this->w, 'multiple' => true)
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
                    array('w' => $this->w, 'multiple' => true)
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
                    array('w' => $this->w, 'multiple' => true)
                );
            }

        }
    }
    
    public function outdateAll($force = false, $persistent = null)
    {
        if (!$force) {
            return (bool) $this->collection->update(
                    array(
                        'persistent' => false,
                        'fresh_until' => array('$gt' => new \MongoDate())
                    ),
                    array('$set' => array(
                        'fresh_until' => new \MongoDate(time() - 1),
                        'persistent' => false,
                        'queue_fresh_until' => new \MongoDate(time() - 1),
                        'queue_persistent' => false,
                        'queued' => false
                    )),
                    array('w' => $this->w, 'multiple' => true)
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
                    array('w' => $this->w, 'multiple' => true)
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
                    array('w' => $this->w, 'multiple' => true)
                );
            }

        }
    }
    
    public function clearQueue()
    {
        return (bool) $this->collection->update(
            array(
                'queued' => true
            ),
            array('$set' => array(
                'queue_fresh_until' => new \MongoDate(time() - 1),
                'queue_persistent' => false,
                'queued' => false
            )),
            array('w' => $this->w, 'multiple' => true)
        );
    }
    
    public function cleanup($outdatedFor = 0)
    {
        return (bool) $this->collection->remove(
                array(
                    '$or' => array(
                        array(
                            'persistent' => false,
                            'fresh_until' => array('$lt' => new \MongoDate(time()-$outdatedFor)),
                            'queued' => false
                            ),
                        array(
                            'persistent' => null,
                            'queued' => false
                        )
                    )
                ),
                array('w' => $this->w, 'multiple' => true, 'timeout' => 0)
            );
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
            array('w' => $this->w)
        );
        return true;
    }
    
}
