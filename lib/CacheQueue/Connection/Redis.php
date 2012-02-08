<?php
namespace CacheQueue\Connection;

class Redis implements ConnectionInterface
{
    private $predis = null;
    private $prefix = null;
    
    private $fields = array(
        '{key}:data' => '',
        '{key}:fresh_until' => 0,
        '{key}:presistent' => 0,
        '{key}:queue_fresh_until' => 0,
        '{key}:queue_persistent' => 0,
        //'{key}:task' => '',
        //'{key}:params' => '',
        '{key}:tags' => array()
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

        $return['tags'] = !empty($result[5]) ? unserialize($result[5]) : array();
        //$return['task'] = !empty($result['task']) ? $result['task'] : null;
        //$return['params'] = !empty($result['params']) ? $result['params'] : null;
        $return['data'] = !empty($result[0]) ? unserialize($result[0]) : false;

        return $return;
    }
    
    public function getValue($key, $onlyFresh = false)
    {
        $result = $this->connection->get($key);
        if (!$result || empty($result['data'])) {
            return false;
        }
        return (!$onlyFresh || $result['is_fresh']) ? $result['data'] : false;
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
            $key.':data',
            $key.':tags',
            $key.':temp'
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
        $return['tags'] = !empty($result[5]) ? unserialize($result[5]) : array();
        $return['temp'] = !empty($result[6]);
        
        return $return;
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

        if ($force) {
            $result = $this->predis->pipeline(function($pipe) use ($key, $data, $freshUntil, $persistent, $tags) {
                $pipe->multi();
                $pipe->mset(array(
                       $key.':data' => serialize($data), 
                       $key.':fresh_until' => $freshUntil,
                       $key.':persistent' => $persistent,
                       $key.':tags' => serialize($tags)
                ));
                foreach ($tags as $tag) {
                    $pipe->sadd('_tag:'.$tag, $key);
                }
                $pipe->exec();
            });

            return $result && !empty($result[count($result)-1]);
        } else {
            $result = $this->predis->pipeline(function($pipe) use ($key) {
                $pipe->watch($key.':fresh_until', $key.':persistent');
                $pipe->mget(array(
                       $key.':fresh_until', 
                       $key.':persistent'
                ));
            });

            if (empty($result[1])) {
                $this->predis->unwatch();
                return false;
            }
            if ($result[1][1] || $result[1][0] > time()) {
                $this->predis->unwatch();
                return true;
            }
        
            $result = $this->predis->pipeline(function($pipe) use ($key, $data, $freshUntil, $persistent, $tags) {
                $pipe->multi();
                $pipe->mset(array(
                       $key.':data' => serialize($data), 
                       $key.':fresh_until' => $freshUntil,
                       $key.':persistent' => $persistent,
                       $key.':tags' => serialize($tags)
                ));
                foreach ($tags as $tag) {
                    $pipe->sadd('_tag:'.$tag, $key);
                }
                $pipe->exec();
            });

            return $result && !empty($result[count($result)-1]);
        }
    }

    public function queue($key, $task, $params, $freshFor, $force = false, $tags = array())
    {
        if ($key === true) {
            $key = 'temp_'.md5(microtime(true).rand(10000,99999));
            $force = true;
            $freshFor = true;
            $temp = 1;
        } else {
            $temp = 0;
        }
        
        if ($freshFor === true) {
            $freshUntil = 0;
            $persistent = 1;
        } else {
            $freshUntil = time() + $freshFor;
            $persistent = 0;
        }

        $tags = array_values((array) $tags);
        
        if ($force) {
            $result = $this->predis->pipeline(function($pipe) use ($key, $task, $params, $freshUntil, $persistent, $tags) {
                $pipe->multi();
                $pipe->mset(array(
                       $key.':task' => $task, 
                       $key.':params' => serialize($params), 
                       $key.':queue_fresh_until' => $freshUntil,
                       $key.':queue_persistent' => $persistent,
                       $key.':queue_tags' => serialize($tags),
                       $key.':temp' => $temp
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
                $this->predis->unwatch();
                return false;
            }
            if ($result[1][1] || $result[1][3] || $result[1][0] > $now || $result[1][2] > $now) {
                $this->predis->unwatch();
                return true;
            }

            $result = $this->predis->pipeline(function($pipe) use ($key, $task, $params, $freshUntil, $persistent, $tags) {
                $pipe->multi();
                $pipe->mset(array(
                       $key.':queue_fresh_until' => $freshUntil, 
                       $key.':queue_persistent' => $persistent,
                       $key.':queue_tags' => serialize($tags), 
                       $key.':task' => $task,
                       $key.':params' => serialize($params), 
                       $key.':temp' => $temp
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
                       $key.':persistent',
                       $key.':tags'
                ));
            });

            if (empty($result[1])) {
                $this->predis->unwatch();
                return false;
            }
            if ($result[1][1] || $result[1][0] > time()) {
                $this->predis->unwatch();
                return true;
            }
            
            $tags = !empty($result[1][2]) ? unserialize($result[1][2]) : array();
        
            $result = $this->predis->pipeline(function($pipe) use ($key, $tags) {
                $pipe->multi();
                $pipe->del(array(
                       $key.':data',
                       $key.':task',
                       $key.':params',
                       $key.':fresh_until',
                       $key.':persistent',
                       $key.':queue_fresh_until',
                       $key.':queue_persistent',
                       $key.':queue_tags',
                       $key.':tags',
                       $key.':temp' 
                ));
                $pipe->srem('_queue', $key);
                foreach ($tags as $tag) {
                    $pipe->srem('_tag:'.$tag, $key);
                }
                $pipe->exec();
            });

            return $result && !empty($result[count($result)-1]);
        } else {
            if ($persistent !== null) {
                $result = $this->predis->pipeline(function($pipe) use ($key) {
                    $pipe->watch($key.':fresh_until', $key.':persistent');
                    $pipe->mget(array(
                           $key.':fresh_until', 
                           $key.':persistent',
                           $key.':tags'
                    ));
                });
                
                if (empty($result[1])) {
                    $this->predis->unwatch();
                    return false;
                }
                if ($result[1][1] != (int) $persistent) {
                    $this->predis->unwatch();
                    return true;
                }
                
                $tags = !empty($result[1][2]) ? unserialize($result[1][2]) : array();

                $result = $this->predis->pipeline(function($pipe) use ($key) {
                    $pipe->multi();
                    $pipe->del(array(
                           $key.':data',
                           $key.':task',
                           $key.':params',
                           $key.':fresh_until',
                           $key.':persistent',
                           $key.':queue_fresh_until',
                           $key.':queue_persistent',
                           $key.':queue_tags',
                           $key.':tags',
                           $key.':temp'
                    ));
                    $pipe->srem('_queue', $key);
                    foreach ($tags as $tag) {
                        $pipe->srem('_tag:'.$tag, $key);
                    }
                    $pipe->exec();
                });

                return $result && !empty($result[count($result)-1]);
            } else {
                $result = $this->predis->get(array(
                       $key.':tags'
                ));
                
                $tags = !empty($result) ? unserialize($result) : array();
                
                $result = $this->predis->pipeline(function($pipe) use ($key, $tags) {
                    $pipe->multi();
                    $pipe->del(array(
                           $key.':data',
                           $key.':task',
                           $key.':params',
                           $key.':fresh_until',
                           $key.':persistent',
                           $key.':queue_fresh_until',
                           $key.':queue_persistent',
                           $key.':queue_tags',
                           $key.':tags',
                           $key.':temp'
                    ));
                    $pipe->srem('_queue', $key);
                    foreach ($tags as $tag) {
                        $pipe->srem('_tag:'.$tag, $key);
                    }
                    $pipe->exec();
                });
                
                return !empty($result[count($result)-1]);
            }
        }
    }
    
    public function removeByTag($tag, $force = false, $persistent = null)
    {
        $tags = array_values((array) $tag);
        $fixedKeys = array();
        foreach ($tags as $tag) {
            $fixedKeys = array_merge($fixedKeys, $this->predis->smembers('_tag:'.$tag));
        }
        
        $reallyFixedKeys = array();
        $entryTags = array();
        foreach ($fixedKeys as $entryTag) {
            $entryTags[$entryTag] = $entryTag.':tags';
        }
        $entryTagsKeys = array_keys($entryTags);
        $entryTagsData = $this->predis->mget(array_values($entryTags));
        foreach ($entryTagsKeys as $k => $v) {
            if ($entryTagsData[$k] && $entryTagsArray = unserialize($entryTagsData[$k])) {
                foreach ($tags as $tag) {
                    if (in_array($tag, $entryTagsArray)) {
                        $reallyFixedKeys[] = $v;
                        continue;
                    }
                }
            }
        }
        
        $fixedKeys = $reallyFixedKeys;
        
        $entries = array(
            'fresh_until' => array(),
            'persistent' => array()
        );
        
        foreach ($fixedKeys as $key) {
            $entries['persistent'][$key] = $key.':persistent';
            $entries['fresh_until'][$key] = $key.':fresh_until';
        }
        
        if ($force && $persistent === null) {
            $result = $this->predis->pipeline(function($pipe) use ($fixedKeys, $tags) {
                $entriesToRemove = array();
                $keysToRemove = array();
                foreach ($fixedKeys as $v) {
                    $entriesToRemove[] = $v.':data';
                    $entriesToRemove[] = $v.':task';
                    $entriesToRemove[] = $v.':params';
                    $entriesToRemove[] = $v.':fresh_until';
                    $entriesToRemove[] = $v.':persistent';
                    $entriesToRemove[] = $v.':queue_fresh_until';
                    $entriesToRemove[] = $v.':queue_persistent';
                    $entriesToRemove[] = $v.':queue_tags';
                    $entriesToRemove[] = $v.':tags';
                    $entriesToRemove[] = $v.':temp';
                    $keysToRemove = $v;
                }
                
                //$entriesToRemove[] = '_queue';
                
                $pipe->multi();
                foreach ($tags as $tag) {
                    $pipe->del('_tag:'.$tag);
                }
                $pipe->del($entriesToRemove);
                $pipe->srem('_queue', $keysToRemove);
                $pipe->exec();
            });
            
            return (bool) $result && !empty($result[count($result)-1]);
        }
        
        if ($force) {
            $persistent = (int) $persistent;
            $persistentKeys = array_keys($entries['persistent']);
            $persistentEntries = array_values($entries['persistent']);
            $matchingEntries = $this->predis->mget($persistentEntries);
            
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
                    $entriesToRemove[] = $v.':queue_tags';
                    $entriesToRemove[] = $v.':tags';
                    $entriesToRemove[] = $v.':temp';
                    $keysToRemove = $v;
                }
            }
            $result = $this->predis->pipeline(function($pipe) use ($entriesToRemove, $keysToRemove, $tags) {
                $pipe->multi();
                $pipe->del($entriesToRemove);
                $pipe->srem('_queue', $keysToRemove);
                foreach ($tags as $tag) {
                    $pipe->srem('_tag:'.$tag, $keysToRemove);
                }
                $pipe->exec();
            });
            return (bool) $result && !empty($result[count($result)-1]);
        } else {
            $freshUntilKeys = array_keys($entries['fresh_until']);
            $freshUntilEntries = array_values($entries['fresh_until']);
            $persistentEntries = str_replace(':fresh_until', ':persistent', $freshUntilEntries);
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
                    $entriesToRemove[] = $v.':queue_tags';
                    $entriesToRemove[] = $v.':tags';
                    $entriesToRemove[] = $v.':temp';
                    $keysToRemove = $v;
                }
            }
            $result = $this->predis->pipeline(function($pipe) use ($entriesToRemove, $keysToRemove, $tags) {
                $pipe->multi();
                $pipe->del($entriesToRemove);
                $pipe->srem('_queue', $keysToRemove);
                foreach ($tags as $tag) {
                    $pipe->srem('_tag:'.$tag, $keysToRemove);
                }
                $pipe->exec();
            });
            return (bool) $result && !empty($result[count($result)-1]);
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
                    $entriesToRemove[] = $v.':queue_tags';
                    $entriesToRemove[] = $v.':tags';
                    $entriesToRemove[] = $v.':temp';
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
                    $entriesToRemove[] = $v.':queue_tags';
                    $entriesToRemove[] = $v.':tags';
                    $entriesToRemove[] = $v.':temp';
                    
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
                $this->predis->unwatch();
                return false;
            }
            if ($result[1][1] || $result[1][0] > time()) {
                $this->predis->unwatch();
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
                    $this->predis->unwatch();
                    return false;
                }
                if ($result[1][1] != (int) $persistent) {
                    $this->predis->unwatch();
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
    
    public function outdateByTag($tag, $force = false, $persistent = null)
    {
        $tags = array_values((array) $tag);
        $fixedKeys = array();
        foreach ($tags as $tag) {
            $fixedKeys = array_marge($fixedKeys, $this->predis->smembers($tag));
        }
        
        $reallyFixedKeys = array();
        $entryTags = array();
        foreach ($fixedKeys as $entryTag) {
            $entryTags[$entryTag] = $entryTag.':tags';
        }
        $entryTagsKeys = array_keys($entryTags);
        $entryTagsData = $this->predis->mget(array_values($entryTags));
        foreach ($entryTagsKeys as $k => $v) {
            if ($entryTagsData[$k] && $entryTagsArray = unserialize($entryTagsData[$k])) {
                foreach ($tags as $tag) {
                    if (in_array($tag, $entryTagsArray)) {
                        $reallyFixedKeys[] = $v;
                        continue;
                    }
                }
            }
        }
        
        $fixedKeys = $reallyFixedKeys;
        
        $entries = array(
            'fresh_until' => array(),
            'persistent' => array()
        );
        
        foreach ($fixedKeys as $key) {
            $entries['persistent'][$key] = $key.':persistent';
            $entries['fresh_until'][$key] = $key.':fresh_until';
        }
        
        if ($force && $persistent === null) {
            $result = $this->predis->pipeline(function($pipe) use ($fixedKeys, $tags) {
                $entriesToOutdate = array();
                foreach ($fixedKeys as $v) {
                    $entriesToOutdate[$v.':persistent'] = true;
                    $entriesToOutdate[$v.':fresh_until'] = true;
                }                
                $pipe->mset($entriesToOutdate);
                $pipe->exec();
            });
            
            return (bool) $result && !empty($result[count($result)-1]);
        } elseif ($force) {
            $persistent = (int) $persistent;
            $persistentKeys = array_keys($entries['persistent']);
            $persistentEntries = array_values($entries['persistent']);
            $matchingEntries = $this->predis->mget($persistentEntries);
            
            $result = $this->predis->pipeline(function($pipe) use ($persistentKeys, $matchingEntries, $persistent, $tags) {
                $entriesToOutdate = array();
                foreach ($persistentKeys as $k => $v) {
                    if ($matchingEntries[$k] == $persistent) {
                        $entriesToOutdate[$v.':fresh_until'] = 0;
                        $entriesToOutdate[$v.':persistent'] = 0;
                    }
                }
                $pipe->mset($entriesToOutdate);
                $pipe->exec();
            });
            
            return (bool) $result && !empty($result[count($result)-1]);
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
            
            $result = $this->predis->pipeline(function($pipe) use ($freshUntilKeys, $result, $persistent, $tags) {
                $now = time();
                $entriesToOutdate = array();
                foreach ($freshUntilKeys as $k => $v) {
                    if (empty($result[1][$k]) && !empty($result[0][$k]) && $result[0][$k] > time()) {
                        $entriesToOutdate[] = $v.':fresh_until';
                        $entriesToOutdate[] = $v.':persistent';
                    }
                }
                $pipe->mset($entriesToOutdate);
                $pipe->exec();
            });
            
            return (bool) $result && !empty($result[count($result)-1]);
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
    
    
    public function obtainLock($key, $lockFor, $timeout)
    {
        $waitUntil = microtime(true) + $timeout;
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
        $result = $this->predis->pipeline(function($pipe) use ($key) {
                $pipe->watch($key.'._lock'.':data');
                $pipe->mget(array(
                       $key.'._lock'.':data'
                ));
            });

            if (empty($result[1])) {
                $this->predis->unwatch();
                return false;
            }
            if ($result[1][0] !== $lockKey) {
                $this->predis->unwatch();
                return false;
            }
            
        
            $result = $this->predis->pipeline(function($pipe) use ($key) {
                $pipe->multi();
                $pipe->del(array(
                       $key.'._lock'.':data',
                       $key.'._lock'.':task',
                       $key.'._lock'.':params',
                       $key.'._lock'.':fresh_until',
                       $key.'._lock'.':persistent',
                       $key.'._lock'.':queue_fresh_until',
                       $key.'._lock'.':queue_persistent',
                       $key.'._lock'.':queue_tags',
                       $key.'._lock'.':tags',
                       $key.'._lock'.':temp' 
                ));
                $pipe->exec();
            });

            return true;
    }
    
}
