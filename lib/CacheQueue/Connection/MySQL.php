<?php
namespace CacheQueue\Connection;

class MySQL implements ConnectionInterface
{
    private $tableName = null;
    
    /**
     * @var \PDO
     */
    private $db = null;
    /**
     * @var \PDOStatement
     */
    private $stmtGet = null;
    /**
     * @var \PDOStatement
     */
    private $stmtGetJob = null;
    /**
     * @var \PDOStatement
     */
    private $stmtUpdateJob = null;
    /**
     * @var \PDOStatement
     */
    private $stmtUpdateJobStatus = null;
    /**
     * @var \PDOStatement
     */
    private $stmtSetGet = null;
    /**
     * @var \PDOStatement
     */
    private $stmtSetInsert = null;
    /**
     * @var \PDOStatement
     */
    private $stmtSetUpdate = null;
    /**
     * @var \PDOStatement
     */
    private $stmtQueueGet = null;
    /**
     * @var \PDOStatement
     */
    private $stmtQueueInsert = null;
    /**
     * @var \PDOStatement
     */
    private $stmtQueueUpdate = null;
    /**
     * @var \PDOStatement
     */
    private $stmtQueueCount = null;
    /**
     * @var \PDOStatement
     */
    private $stmtReleaseLock = null;
    
    public function __construct($config = array())
    {
        
        $this->db = new \PDO($config['dns'], $config['user'], $config['pass'], !empty($config['options']) ? $config['options'] : array());
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        if ($this->db && $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME) == 'mysql') {
            $this->db->exec('SET CHARACTER SET utf8');
        }

        $this->tableName = !empty($config['table']) ? $config['table'] : 'cache';
    }
    
    public function setup()
    {
        $this->db->query('CREATE TABLE '.$this->tableName.' (
            id VARCHAR(250),
            fresh_until BIGINT NOT NULL DEFAULT 0,
            persistent TINYINT(1) NOT NULL DEFAULT 0,
            tags VARCHAR(250) NOT NULL DEFAULT "",
            queued TINYINT(1) NOT NULL DEFAULT 0,
            queued_worker INT(11),
            queue_fresh_until BIGINT NOT NULL DEFAULT 0,
            queue_persistent TINYINT(1) NOT NULL DEFAULT 0,
            queue_tags VARCHAR(250) NOT NULL DEFAULT "",
            queue_priority INT(11) NOT NULL DEFAULT 0,
            date_set BIGINT NOT NULL DEFAULT 0,
            is_temp TINYINT(1) NOT NULL DEFAULT 0,
            task VARCHAR(250),
            params BLOB,
            data LONGBLOB,
            PRIMARY KEY (id),
            INDEX fresh_until (fresh_until),
            INDEX persistent (persistent),
            INDEX queued (queued),
            INDEX queue_fresh_until (queue_fresh_until),
            INDEX queue_persistent (queue_persistent),
            INDEX queue_priority (queue_priority),
            INDEX tags (tags)
            ) ENGINE=INNODB DEFAULT CHARSET=utf8
            '
        );

    }

    public function get($key)
    {
        $stmt = $this->stmtGet ?: $this->stmtGet = $this->db->prepare('SELECT id, fresh_until, persistent, queue_fresh_until, queue_persistent, date_set, task, params, data, tags FROM '.$this->tableName.' WHERE id = ? LIMIT 1');
        if (!$stmt->execute(array($key))) {
            return false;
        }
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if (!$result) {
            return false;
        }
        
        $return = array();
        
        $return['key'] = $result['id'];
        //$return['queued'] = !empty($result['queued']);
        $return['fresh_until'] = !empty($result['fresh_until']) ? $result['fresh_until'] : 0;
        $return['persistent'] = !empty($result['persistent']);
        $return['is_fresh'] = $return['persistent'] || $return['fresh_until'] > time();

        $return['date_set'] = !empty($result['date_set']) ? $result['date_set'] : 0;
        
        $return['queue_fresh_until'] = !empty($result['queue_fresh_until']) ? $result['queue_fresh_until'] : 0;
        $return['queue_persistent'] = !empty($result['queue_persistent']);
        $return['queue_is_fresh'] = $return['queue_persistent'] || $return['queue_fresh_until'] > time();
        $return['tags'] = !empty($result['tags']) ? explode('##', mb_substr($result['tags'], 2, mb_strlen($result['tags']), 'UTF-8')) : array();
        $return['task'] = !empty($result['task']) ? $result['task'] : null;
        $return['params'] = !empty($result['params']) ? unserialize($result['params']) : null;
        $return['data'] = isset($result['data']) ? unserialize($result['data']) : false;

        return $return;
    }
    
    
    public function getByTag($tag, $onlyFresh = false)
    {
        
        $tags = array_values((array) $tag);
        $return = array();
        
        $query = 'SELECT id, fresh_until, persistent, queue_fresh_until, queue_persistent, date_set, task, params, data, tags FROM '.$this->tableName.' WHERE';
        
        $query .= ' (tags LIKE "%##'.implode('%" OR tags LIKE "%##', $tags).'%") ';
        
        if ($onlyFresh) {
            $query .= ' AND (fresh_until > NOW() OR persistent = 1)';
        }
        
        
        $stmt = $this->db->query($query);
        if (!$stmt = $this->db->query($query)) {
            return false;
        }
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $result) {
            $entry = array();
            $entry['key'] = $result['id'];
            //$return['queued'] = !empty($result['queued']);
            $entry['fresh_until'] = !empty($result['fresh_until']) ? $result['fresh_until'] : 0;
            $entry['persistent'] = !empty($result['persistent']);
            $entry['is_fresh'] = $entry['persistent'] || $entry['fresh_until'] > time();

            $entry['date_set'] = !empty($result['date_set']) ? $result['date_set'] : 0;

            $entry['queue_fresh_until'] = !empty($result['queue_fresh_until']) ? $result['queue_fresh_until'] : 0;
            $entry['queue_persistent'] = !empty($result['queue_persistent']);
            $entry['queue_is_fresh'] = $entry['queue_persistent'] || $entry['queue_fresh_until'] > time();
            $entry['tags'] = !empty($entry['tags']) ? explode('##', mb_substr($entry['tags'], 2, mb_strlen($entry['tags']), 'UTF-8')) : array();
            $entry['task'] = !empty($result['task']) ? $result['task'] : null;
            $entry['params'] = !empty($result['params']) ? unserialize($result['params']) : null;
            $entry['data'] = isset($result['data']) ? unserialize($result['data']) : false;
            
            $return[] = $entry;
        }
        
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
        $this->db->beginTransaction();
        $stmt = $this->stmtGetJob ?: $this->stmtGetJob = $this->db->prepare('SELECT id, queue_fresh_until, queue_persistent, queue_tags, task, params, data, is_temp FROM '.$this->tableName.' WHERE queued = 1 ORDER BY queue_priority ASC LIMIT 1 FOR UPDATE');
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (empty($result)) {
            $this->db->commit();
            return false;
        }
        $stmt = $this->stmtUpdateJob ?: $this->stmtUpdateJob = $this->db->prepare('UPDATE '.$this->tableName.' SET queued = 0, queued_worker = ? WHERE id = ?');
        $stmt->execute(array($workerId, $result['id']));
        $this->db->commit();
        
        $return = array();
        
        $return['key'] = $result['id'];
        $return['fresh_until'] = !empty($result['queue_fresh_until']) ? $result['queue_fresh_until'] : 0;
        $return['persistent'] = !empty($result['queue_persistent']);
        $return['tags'] = !empty($result['queue_tags']) ? explode('##', mb_substr($result['queue_tags'], 2, mb_strlen($result['queue_tags']), 'UTF-8')) : array();
        $return['task'] = !empty($result['task']) ? $result['task'] : null;
        $return['params'] = !empty($result['params']) ? unserialize($result['params']) : null;
        $return['data'] = isset($result['data']) ? unserialize($result['data']) : null;
        $return['temp'] = !empty($result['is_temp']);
        $return['worker_id'] = $workerId;
        
        return $return;
    }
    
    public function updateJobStatus($key, $workerId)
    {
        $stmt = $this->stmtUpdateJobStatus ?: $this->stmtUpdateJobStatus = $this->db->prepare('UPDATE '.$this->tableName.' SET queued_worker = null, queue_fresh_until = 0, queue_persistent = 0 WHERE queued_worker = ? AND id = ?');
        return $stmt->execute(array($workerId, $key));
    }
    
    public function set($key, $data, $freshFor, $force = false, $tags = array())
    {
        if ($freshFor === true) {
            $freshUntil = 0;
            $persistent = 1;
        } else {
            $freshUntil = time() + $freshFor;
            $persistent = 0;
        }
        
        $tags = array_values((array) $tags);
        
        try {
            $this->db->beginTransaction();
            $stmt = $this->stmtSetGet ?: $this->stmtSetGet = $this->db->prepare('SELECT id, fresh_until, persistent FROM '.$this->tableName.' WHERE id = ? LIMIT 1 FOR UPDATE');
            $stmt->execute(array($key));
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (empty($result)) {
                $stmt = $this->stmtSetInsert ?: $this->stmtSetInsert = $this->db->prepare('INSERT INTO '.$this->tableName.' SET
                    id = ?,
                    fresh_until = ?,
                    persistent = ?,
                    data = ?,
                    date_set = ?,
                    tags = ?
                    ');
                $stmt->execute(array($key, $freshUntil, $persistent, serialize($data), time(), !empty($tags) ? '##'.implode('##', $tags) : ''));
                $this->db->commit();
                return true;
            }
            
            if ($force || ($result['fresh_until'] < time() && empty($result['persistent']))) {
                $stmt = $this->stmtSetUpdate ?: $this->stmtSetUpdate = $this->db->prepare('UPDATE '.$this->tableName.' SET
                    fresh_until = ?,
                    persistent = ?,
                    data = ?,
                    date_set = ?,
                    tags = ?
                    WHERE id = ?
                    ');
                $stmt->execute(array($freshUntil, $persistent, serialize($data), time(), !empty($tags) ? '##'.implode('##', $tags) : '', $key));
                $this->db->commit();
                return true;
            } else {
                $this->db->commit();
                return true;
            }   
        } catch (\PDOException $e) {
            $this->db->rollBack();
            return false;
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
            $freshUntil = 0;
            $persistent = 1;
        } else {
            $freshUntil = time() + $freshFor;
            $persistent = 0;
        }
        
        $tags = array_values((array) $tags);
        
        try {
            $this->db->beginTransaction();
            $stmt = $this->stmtQueueGet ?: $this->stmtQueueGet = $this->db->prepare('SELECT id, fresh_until, persistent, queue_fresh_until, queue_persistent FROM '.$this->tableName.' WHERE id = ? LIMIT 1 FOR UPDATE');
            $stmt->execute(array($key));
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (empty($result)) {
                $stmt = $this->stmtQueueInsert ?: $this->stmtQueueInsert = $this->db->prepare('INSERT INTO '.$this->tableName.' SET
                    id = ?,
                    queue_fresh_until = ?,
                    queue_persistent = ?,
                    queued = 1,
                    queued_worker = null,
                    task = ?,
                    params = ?,
                    queue_priority = ?,
                    queue_tags = ?,
                    is_temp = ?
                    ');
                $stmt->execute(array($key, $freshUntil, $persistent, $task, serialize($params), $priority, !empty($tags) ? '##'.implode('##', $tags) : '', $temp ? 1 : 0));
                $this->db->commit();
                return true;
            }
            
            if ($force || ($result['fresh_until'] < time() && $result['queue_fresh_until'] < time() && empty($result['persistent']) && empty($result['queue_persistent']))) {
                $stmt = $this->stmtQueueUpdate ?: $this->stmtQueueUpdate = $this->db->prepare('UPDATE '.$this->tableName.' SET
                    queue_fresh_until = ?,
                    queue_persistent = ?,
                    queued = 1,
                    queued_worker = null,
                    task = ?,
                    params = ?,
                    queue_priority = ?,
                    queue_tags = ?,
                    is_temp = ?
                    WHERE id = ?
                    ');
                $stmt->execute(array($key, $freshUntil, $persistent, $task, serialize($params), $priority, !empty($tags) ? '##'.implode('##', $tags) : '', $temp ? 1 : 0, $key));
                $this->db->commit();
                return true;
            } else {
                $this->db->commit();
                return true;
            }   
        } catch (\PDOException $e) {
            $this->db->rollBack();
            return false;
        }

    }

    public function getQueueCount()
    {
        $stmt = $this->stmtQueueCount ?: $this->stmtQueueCount = $this->db->prepare('SELECT COUNT(*) FROM '.$this->tableName.' WHERE queued = 1');
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    public function remove($key, $force = false, $persistent = null)
    {
        $query = 'DELETE FROM '.$this->tableName.' WHERE id = ? ';
        $values = array($key);
        if (!$force) {
            $query .= ' AND fresh_until < ? and persistent != 1';
            $values[] = time();
        } else {
            if ($persistent !== null) {
                $query .= 'AND persistent = ?';
                $values[] = (int) $persistent;
            }
        }
        $stmt = $this->db->prepare($query);
        return $stmt->execute($values);
    }
    
    public function removeByTag($tag, $force = false, $persistent = null)
    {
        $tags = array_values((array) $tag);
        $query = 'DELETE FROM '.$this->tableName.' WHERE ';
        
        $query .= ' (tags LIKE "%##'.implode('%" OR tags LIKE "%%', $tags).'%") ';
        
        $values = array();
        if (!$force) {
            $query .= ' AND fresh_until < ? and persistent != 1';
            $values[] = time();
        } else {
            if ($persistent !== null) {
                $query .= 'AND persistent = ?';
                $values[] = (int) $persistent;
            }
        }
        $stmt = $this->db->prepare($query);
        return $stmt->execute($values);
    }
    
    public function removeAll($force = false, $persistent = null)
    {
        $query = 'DELETE FROM '.$this->tableName.' ';
        $values = array();
        if (!$force) {
            $query .= ' WHERE fresh_until < ? and persistent != 1';
            $values[] = time();
        } else {
            if ($persistent !== null) {
                $query .= 'WHERE persistent = ?';
                $values[] = (int) $persistent;
            }
        }
        $stmt = $this->db->prepare($query);
        return $stmt->execute($values);
    }
    
    public function outdate($key, $force = false, $persistent = null)
    {
        $query = 'UPDATE '.$this->tableName.' SET
            fresh_until = ?,
            queue_fresh_until = 0,
            persistent = 0,
            queue_persistent = 0,
            queued = 0
            WHERE id = ? ';
        
        $values = array(time()-1, $key);
        if (!$force) {
            $query .= ' AND fresh_until < ? and persistent != 1';
            $values[] = time();
        } else {
            if ($persistent !== null) {
                $query .= 'AND persistent = ?';
                $values[] = (int) $persistent;
            }
        }
        $stmt = $this->db->prepare($query);
        return $stmt->execute($values);
    }
    
    public function outdateByTag($tag, $force = false, $persistent = null)
    {
        $tags = array_values((array) $tag);
        $query = 'UPDATE '.$this->tableName.' SET
            fresh_until = ?,
            queue_fresh_until = 0,
            persistent = 0,
            queue_persistent = 0,
            queued = 0
            WHERE ';
        
        $query .= ' (tags LIKE "%##'.implode('%" OR tags LIKE "%%', $tags).'%") ';
        
        $values = array(time()-1);
        if (!$force) {
            $query .= ' AND fresh_until < ? and persistent != 1';
            $values[] = time();
        } else {
            if ($persistent !== null) {
                $query .= 'AND persistent = ?';
                $values[] = (int) $persistent;
            }
        }
        $stmt = $this->db->prepare($query);
        return $stmt->execute($values);
    }
    
    public function outdateAll($force = false, $persistent = null)
    {
        $query = 'UPDATE '.$this->tableName.' SET
            fresh_until = ?,
            queue_fresh_until = 0,
            persistent = 0,
            queue_persistent = 0,
            queued = 0
            ';
        
        $values = array(time()-1);
        if (!$force) {
            $query .= ' WHERE fresh_until < ? and persistent != 1';
            $values[] = time();
        } else {
            if ($persistent !== null) {
                $query .= 'WHERE persistent = ?';
                $values[] = (int) $persistent;
            }
        }
        $stmt = $this->db->prepare($query);
        return $stmt->execute($values);
    }
    
    public function cleanup($outdatedFor = 0)
    {
        $query = 'DELETE FROM '.$this->tableName.' WHERE fresh_until < ? and persistent != 1';
        $stmt = $this->db->prepare($query);
        return $stmt->execute(array(time()-$outdatedFor));
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
        $stmt = $this->stmtReleaseLock ?: $this->stmtReleaseLock = $this->db->prepare('DELETE FROM '.$this->tableName. ' WHERE id = ? AND data = ?');
        $stmt->execute(array($key.'._lock', serialize($lockKey)));
        return true;
    }
    
}
