<?php
namespace CacheQueue;

class RedisConnection implements IConnection
{
    private $predis = null;
    private $prefix = null;
    
    private $fields = array(
        '{key}:data' => '',
        '{key}:fresh_until' => 0,
        '{key}:presistent' => 0,
        '{key}:queue_fresh_until' => 0,
        '{key}:queue_persistent' => 0,
        '{key}:task' => '',
        '{key}:params' => ''
    );
    
    public function __construct($config = array())
    {
        if (!empty($config['predisFile'])) {
            require_once($config['predisFile']);
        }
        
        $this->predis = new \Predis\Client(
                !empty($config['parameters']) ? $config['parameters'] : null,
                !empty($config['options']) ? $config['options'] : null
        );
        
        $this->prefix = !empty($config['options']['prefix']) ? $config['options']['prefix'] : '';
    }
    
    public function setup()
    {
    }

    public function get($key)
    {
        $result = $this->predis->mget(str_replace('{key}', $key, array_keys($this->fields)));

        if (!$result || $result[0] === null) {
            return false;
        }
        $return = array();
        
        $return['key'] = $key;
        //$return['queued'] = !empty($result['queued']);
        $return['fresh_until'] = !empty($result[1]) ? $result[1] : 0;
        $return['persistent'] = !empty($result[2]);
        $return['is_fresh'] = $return['persistent'] || $return['fresh_until'] > time();

        $return['queue_is_fresh'] = !empty($result[4]) || (!empty($result[3]) && $result[3] > time());

        //$return['task'] = !empty($result['task']) ? $result['task'] : null;
        //$return['params'] = !empty($result['params']) ? $result['params'] : null;
        $return['data'] = !empty($result[0]) ? unserialize($result[0]) : false;

        return $return;
    }

    public function getJob()
    {
        $result = $this->predis->spop('_queue');
        
        if (empty($result)) {
            return false;
        }
        
        $key = $result;
        
        $result = $this->predis->mget(array(
            $key.':queue_fresh_until',
            $key.':queue_persistent',
            $key.':task',
            $key.':params',
            $key.':data'
        ));
        
        if (empty($result)) {
            return false;
        }
        
        $return['key'] = $key;
        $return['fresh_until'] = !empty($result[0]) ? $result[0] : 0;
        $return['persistent'] = !empty($result[1]);
        $return['task'] = !empty($result[2]) ? $result[2] : null;
        $return['params'] = !empty($result[3]) ? unserialize($result[3]) : null;
        $return['data'] = !empty($result[4]) ? unserialize($result[4]) : null;
        
        return $return;
    }
    
    public function set($key, $data, $freshFor, $force = false)
    {
        if ($freshFor === true) {
            $freshUntil = 0;
            $persistent = 1;
        } else {
            $freshUntil = time() + $freshFor;
            $persistent = 0;
        }

        if ($force) {
            $result = $this->predis->mset(array(
                   $key.':data' => serialize($data), 
                   $key.':fresh_until' => $freshUntil,
                   $key.':persistent' => $persistent
            ));
            return (bool) $result;
        } else {
            $result = $this->predis->pipeline(function($pipe) use ($key) {
                $pipe->watch($key.':fresh_until', $key.':persistent');
                $pipe->mget(array(
                       $key.':fresh_until', 
                       $key.':persistent'
                ));
            });

            if (empty($result[1])) {
                return false;
            }
            if ($result[1][1] || $result[1][0] > time()) {
                return true;
            }
        
            $result = $this->predis->pipeline(function($pipe) use ($key, $data, $freshUntil, $persistent) {
                $pipe->multi();
                $pipe->mset(array(
                       $key.':data' => serialize($data), 
                       $key.':fresh_until' => $freshUntil,
                       $key.':persistent' => $persistent
                ));
                $pipe->exec();
            });

            return $result && !empty($result[2]);
        }
    }

    public function queue($key, $task, $params, $freshFor, $force = false)
    {
        if ($freshFor === true) {
            $freshUntil = 0;
            $persistent = 1;
        } else {
            $freshUntil = time() + $freshFor;
            $persistent = 0;
        }

        if ($force) {
            $result = $this->predis->pipeline(function($pipe) {
                $pipe->multi();
                $pipe->mset(array(
                       $key.':task' => $task, 
                       $key.':params' => serialize($params), 
                       $key.':queue_fresh_until' => $freshUntil,
                       $key.':queue_persistent' => $persistent
                ));
                $pipe->sadd('_queue', $key);
                $pipe->exec();
            });
            return $result && !empty($result[3]);
        } else {
            $now = time();
            
            $result = $this->predis->pipeline(function($pipe) use ($key) {
                $pipe->watch($key.':fresh_until', $key.':persistent');
                $pipe->mget(array(
                       $key.':fresh_until', 
                       $key.':persistent', 
                       $key.':queue_fresh_until',
                       $key.':queue_persistent'
                ));
            });

            if (empty($result[1])) {
                return false;
            }
            if ($result[1][1] || $result[1][3] || $result[1][0] > $now || $result[1][2] > $now) {
                return true;
            }

            $result = $this->predis->pipeline(function($pipe) use ($key, $task, $params, $freshUntil, $persistent) {
                $pipe->multi();
                $pipe->mset(array(
                       $key.':queue_fresh_until' => $freshUntil, 
                       $key.':queue_persistent' => $persistent, 
                       $key.':task' => $task,
                       $key.':params' => serialize($params), 
                ));
                $pipe->sadd('_queue', $key);
                $pipe->exec();
            });

            return $result && !empty($result[3]);
        }
    }

    public function getQueueCount()
    {
        return $this->predis->scard('_queue');
    }
    
    public function remove($key, $force = false, $persistent = null)
    {

        if (!$force) {
            $result = $this->predis->pipeline(function($pipe) use ($key) {
                $pipe->watch($key.':fresh_until', $key.':persistent');
                $pipe->mget(array(
                       $key.':fresh_until', 
                       $key.':persistent'
                ));
            });

            if (empty($result[1])) {
                return false;
            }
            if ($result[1][1] || $result[1][0] > time()) {
                return true;
            }
        
            $result = $this->predis->pipeline(function($pipe) use ($key) {
                $pipe->multi();
                $pipe->del(array(
                       $key.':data',
                       $key.':task',
                       $key.':params',
                       $key.':fresh_until',
                       $key.':persistent',
                       $key.':queue_fresh_until',
                       $key.':queue_persistent'
                ));
                $pipe->srem('_queue', $key);
                $pipe->exec();
            });

            return $result && !empty($result[3]);
        } else {
            if ($persistent !== null) {
                $result = $this->predis->pipeline(function($pipe) use ($key) {
                    $pipe->watch($key.':fresh_until', $key.':persistent');
                    $pipe->mget(array(
                           $key.':fresh_until', 
                           $key.':persistent'
                    ));
                });
                
                if (empty($result[1])) {
                    return false;
                }
                if ($result[1][1] != (int) $persistent) {
                    return true;
                }

                $result = $this->predis->pipeline(function($pipe) use ($key) {
                $pipe->multi();
                $pipe->del(array(
                       $key.':data',
                       $key.':task',
                       $key.':params',
                       $key.':fresh_until',
                       $key.':persistent',
                       $key.':queue_fresh_until',
                       $key.':queue_persistent'
                ));
                $pipe->srem('_queue', $key);
                $pipe->exec();
                });

                return $result && !empty($result[3]);
            } else {
                $result = $this->predis->pipeline(function($pipe) use ($key) {
                    $pipe->multi();
                    $pipe->del(array(
                           $key.':data',
                           $key.':task',
                           $key.':params',
                           $key.':fresh_until',
                           $key.':persistent',
                           $key.':queue_fresh_until',
                           $key.':queue_persistent'
                    ));
                    $pipe->srem('_queue', $key);
                    $pipe->exec();
                });
                
                return !empty($result[3]);
            }
        }
    }
    
    public function removeAll($force = false, $persistent = null)
    {
        $keys = $this->predis->keys($this->prefix.'*');
        $fixedKeys = array();
        
        $prefixlength = strlen($this->prefix);
        $entries = array();
        
        foreach ($keys as $key) {
            $fixedKey = substr($key, $prefixlength - 1);
            $fixedKeys[] = $fixedKey;
            $tmp = explode(':', $fixedKey, 2);
            $entries[$tmp[1]][$tmp[0]] = $fixedKey;
        }
        
        if ($force && $persistent === null) {
            return (bool) $this->predis->del($fixedKeys);
        }
        
        if ($force) {
            $persistent = (int) $persistent;
            $persistentKeys = array_keys($entries['persistent']);
            $persistentEntries = array_values($entries['persistent']);
            $matchingEntries = $this->predis->mget($persistentValues);
            
            $entriesToRemove = array();
            $keysToRemove = array();
            foreach ($persistentKeys as $k => $v) {
                if ($matchingEntries[$k] == $persistent) {
                    $entriesToRemove[] = $v.':data';
                    $entriesToRemove[] = $v.':task';
                    $entriesToRemove[] = $v.':params';
                    $entriesToRemove[] = $v.':fresh_until';
                    $entriesToRemove[] = $v.':persistent';
                    $entriesToRemove[] = $v.':queue_fresh_until';
                    $entriesToRemove[] = $v.':queue_persistent';
                    
                    $keysToRemove = $v;
                }
            }
            $result = $this->predis->pipeline(function($pipe) use ($entriesToRemove, $keysToRemove) {
                $pipe->multi();
                $pipe->del($entriesToRemove);
                $pipe->srem('_queue', $keysToRemove);
                $pipe->exec();
            });
            return !empty($result[3]);
        } else {
            $freshUntilKeys = array_keys($entries['fresh_until']);
            $freshUntilEntries = array_values($entries['fresh_until']);
            $persistentEntries = str_replace(':fresh_until', ':persistent', $freshUntilKeys);
            $result = $this->predis->pipeline(function($pipe) use ($freshUntilEntries, $persistentEntries) {
                $pipe->mget($freshUntilEntries);
                $pipe->mget($persistentEntries);
            });
            
            if (empty($result)) {
                return false;
            }
            
            $now = time();
            
            $entriesToRemove = array();
            $keysToRemove = array();
            foreach ($freshUntilKeys as $k => $v) {
                if (empty($result[1][$k]) && (empty($result[0][$k]) || $result[0][$k] < time())) {
                    $entriesToRemove[] = $v.':data';
                    $entriesToRemove[] = $v.':task';
                    $entriesToRemove[] = $v.':params';
                    $entriesToRemove[] = $v.':fresh_until';
                    $entriesToRemove[] = $v.':persistent';
                    $entriesToRemove[] = $v.':queue_fresh_until';
                    $entriesToRemove[] = $v.':queue_persistent';
                    
                    $keysToRemove = $v;
                }
            }
            $result = $this->predis->pipeline(function($pipe) use ($entriesToRemove, $keysToRemove) {
                $pipe->multi();
                $pipe->del($entriesToRemove);
                $pipe->srem('_queue', $keysToRemove);
                $pipe->exec();
            });
            return !empty($result[3]);
        }
    }
    
    public function outdate($key, $force = false, $persistent = null)
    {

        if (!$force) {
            $result = $this->predis->pipeline(function($pipe) use ($key) {
                $pipe->watch($key.':fresh_until', $key.':persistent');
                $pipe->mget(array(
                       $key.':fresh_until', 
                       $key.':persistent'
                ));
            });

            if (empty($result[1])) {
                return false;
            }
            if ($result[1][1] || $result[1][0] > time()) {
                return true;
            }
        
            $result = $this->predis->pipeline(function($pipe) use ($key) {
                $pipe->multi();
                $pipe->mset(array(
                       $key.':fresh_until' => 0,
                       $key.':persistent' => 0
                ));
                $pipe->exec();
            });

            return $result && !empty($result[2]);
        } else {
            if ($persistent !== null) {
                $result = $this->predis->pipeline(function($pipe) use ($key) {
                    $pipe->watch($key.':fresh_until', $key.':persistent');
                    $pipe->mget(array(
                           $key.':fresh_until', 
                           $key.':persistent'
                    ));
                });
                
                if (empty($result[1])) {
                    return false;
                }
                if ($result[1][1] != (int) $persistent) {
                    return true;
                }

                $result = $this->predis->pipeline(function($pipe) use ($key) {
                    $pipe->multi();
                    $pipe->mset(array(
                           $key.':fresh_until' => 0,
                           $key.':persistent' => 0
                    ));
                    $pipe->exec();
                });

                return $result && !empty($result[2]);
            } else {
                return (bool) $this->predis->mset(array(
                       $key.':fresh_until' => 0,
                       $key.':persistent' => 0
                ));
            }
        }
    }
    
    public function outdateAll($force = false, $persistent = null)
    {
        $keys = $this->predis->keys($this->prefix.'*');
        $fixedKeys = array();
        $prefixlength = strlen($this->prefix);
        $entries = array();
        
        foreach ($keys as $key) {
            $fixedKey = substr($key, $prefixlength - 1);
            $fixedKeys[] = $fixedKey;
            $tmp = explode(':', $fixedKey, 2);
            $entries[$tmp[1]][$tmp[0]] = $fixedKey;
        }
        
        if ($force && $persistent === null) {
            $entriesToOutdate = array();
            foreach ($entries['fresh_until'] as $k => $v) {
                $entriesToOutdate[$k.':persistent'] = true;
                $entriesToOutdate[$k.':fresh_until'] = true;
            }
            foreach ($entries['persistent'] as $k => $v) {
                $entriesToOutdate[$k.':persistent'] = 0;
                $entriesToOutdate[$k.':fresh_until'] = 0;
            }
            return (bool) $this->predis->mset($entriesToOutdate);
        }
        
        if ($force) {
            $persistent = (int) $persistent;
            $persistentKeys = array_keys($entries['persistent']);
            $persistentEntries = array_values($entries['persistent']);
            $matchingEntries = $this->predis->mget($persistentValues);
            
            $entriesToOutdate = array();
            foreach ($persistentKeys as $k => $v) {
                if ($matchingEntries[$k] == $persistent) {
                    $entriesToOutdate[$v.':fresh_until'] = 0;
                    $entriesToOutdate[$v.':persistent'] = 0;
                }
            }
            return $this->predis->mset($entriesToOutdate);
        } else {
            $freshUntilKeys = array_keys($entries['fresh_until']);
            $freshUntilEntries = array_values($entries['fresh_until']);
            $persistentEntries = str_replace(':fresh_until', ':persistent', $freshUntilKeys);
            $result = $this->predis->pipeline(function($pipe) use ($freshUntilEntries, $persistentEntries) {
                $pipe->mget($freshUntilEntries);
                $pipe->mget($persistentEntries);
            });
            
            if (empty($result)) {
                return false;
            }
            
            $now = time();
            
            $entriesToOutdate = array();
            foreach ($freshUntilKeys as $k => $v) {
                if (empty($result[1][$k]) && !empty($result[0][$k]) && $result[0][$k] > time()) {
                    $entriesToOutdate[] = $v.':fresh_until';
                    $entriesToOutdate[] = $v.':persistent';
                }
            }
            return $this->predis->mset($entriesToOutdate);
        }
    }
    
}
