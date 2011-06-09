<?php
namespace CacheQueue;

class MongoConnection implements IConnection
{
    private $dbName = null;
    private $collectionName = null;
    
    private $db = null;
    private $collection = null;
    
    public function __construct($config)
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
    }

    public function get($key)
    {
        $result = $this->collection->findOne(array('_id' => $key));
        if (!$result) {
            return false;
        }
        $return = array();
        
        $return['key'] = $result['_id'];
        $return['queued'] = !empty($result['queued']);
        $return['fresh_until'] = !empty($result['fresh_until']) ? $result['fresh_until']->sec : 0;
        $return['is_fresh'] = $return['fresh_until'] > time();
        $return['task'] = !empty($result['task']) ? $result['task'] : null;
        $return['params'] = !empty($result['params']) ? $result['params'] : null;
        $return['data'] = !empty($result['data']) ? $result['data'] : null;

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
        $return['fresh_until'] = !empty($result['value']['fresh_until']) ? $result['value']['fresh_until']->sec : 0;
        $return['is_fresh'] = $return['fresh_until'] > time();
        $return['task'] = !empty($result['value']['task']) ? $result['value']['task'] : null;
        $return['params'] = !empty($result['value']['params']) ? $result['value']['params'] : null;
        $return['data'] = !empty($result['value']['data']) ? $result['value']['data'] : null;
        
        return $return;
    }

    public function queue($key, $task, $params, $freshUntil)
    {
        return (bool) $this->collection->update(
                array(
                    '_id' => $key,
                    'fresh_until' => array('$lt' => new \MongoDate())
                ),
                array('$set' => array(
                    'fresh_until' => new \MongoDate($freshUntil),
                    'queued' => true,
                    'task' => $task,
                    'params' => $params
                )),
                array('upsert' => true)
            );
    }

    public function setData($key, $data)
    {
        return (bool) $this->collection->update(
                array(
                    '_id' => $key
                ),
                array('$set' => array(
                    'data' => $data
                ))
            );
    }

    public function getQueueCount()
    {
        return $this->collection->count(array('queued' => true));
    }
    
    public function cleanup($maxFreshUntil)
    {
        return (bool) $this->collection->remove(array(
                'fresh_until' => array('$lt' => new \MongoDate($maxFreshUntil))
        ));    
    }
}
