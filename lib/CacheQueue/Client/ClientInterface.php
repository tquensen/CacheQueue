<?php
namespace CacheQueue\Client;
use CacheQueue\Connection\ConnectionInterface;

interface ClientInterface
{

    public function __construct(ConnectionInterface $connection, $config = array());

    /**
     * get a cached entries value
     * 
     * @param string $key the key to get
     * @param bool $onlyFresh true to return the value only if it is fresh, false (default) to return also outdated values
     * @return mixed the value or false if not found 
     */
    public function get($key, $onlyFresh = false);
    
    /**
     * get a cached entry
     * 
     * @param string $key the key to get
     * @return mixed the result array or false if not found 
     */
    public function getEntry($key);

    /**
     * save cache data
     * 
     * @param string $key the key to save the data for
     * @param mixed $data the data to be saved
     * @param int|bool $freshFor number of seconds that the data is fresh or true to store as persistent
     * @param bool $force true to force the save even if the data is still fresh,
     * @param array|string $tags one or multiple tags to assign to the cache entry
     * @return bool if the save was sucessful 
     */
    public function set($key, $data, $freshFor, $force = false, $tags = array());

    /**
     * add a queue entry 
     * 
     * @param string $key the key to save the data for or true for a random key
     * @param string $task the task to run
     * @param mixed $params parameters for the task
     * @param int|bool $freshFor number of seconds that the data is fresh or true to store as persistent
     * @param bool $force true to force the queue even if the data is still fresh
     * @param array|string $tags one or multiple tags to assign to the cache entry
     * @return bool if the queue was sucessful 
     */
    public function queue($key, $task, $params, $freshFor, $force = false, $tags = array());
    
    /**
     * add a temporary queue entry which gets deleted after the job was executed
     * 
     * this will add a persistent queue entry with random key to the queue,
     * which gets deleted after the job was executed
     * 
     * @param string $task the task to run
     * @param mixed $params parameters for the task
     * @param array|string $tags one or multiple tags to assign to the cache entry
     * @return bool if the queue was sucessful 
     */
    public function queueTemporary($task, $params, $tags = array());

    /**
     * get the data for key from cache, run callback and store the data if its not fresh 
     * 
     * @param string $key the key to get
     * @param mixed $callback a valid php callable to get the data from if the cache was outdated
     * @param mixed $params parameters for the callback
     * @param int|bool $freshFor number of seconds that the data is fresh or true to store as persistent
     * @param bool $force true to force the save even if the data is still fresh
     * @return mixed the cached or generated data
     */
    public function getOrSet($key, $callback, $params, $freshFor, $force = false, $tags = array());

    /**
     * get the data for key from cache, queue a task if its not fresh 
     * 
     * @param string $key the key to save the data for
     * @param string $task the task to run if the cached data was outdated
     * @param mixed $params parameters for the task
     * @param int|bool $freshFor number of seconds that the data is fresh or true to store as persistent
     * @param bool $force true to force the queue even if the data is still fresh
     * @return mixed the cached data or false if not found
     */
    public function getOrQueue($key, $task, $params, $freshFor, $force = false, $tags = array());
    
    /**
     * removes an entry from cache
     * 
     * @param string $key the key of the entry to remove from cache
     * @param bool $force if false (default), the entry will only be removed if it is outdated and non persistent 
     * @param type $persistent only used if force=true. if true, the entry will be removed only if it is persistent, if false only if it is non-persistent, if null (default) if will be removed regardless of the persistent state
     * @return bool if the request was successful 
     */
    public function remove($key, $force = false, $persistent = null);
    
    /**
     * removes all entries from cache
     * 
     * @param bool $force if false (default), only fresh, non persistent entries will be removed 
     * @param type $persistent only used if force=true. if true, only persistent entries will be removed, if false only non-persistent entries will be removed, if null(default) both persistent and non persistent entries will be removed
     * @return bool if the request was successful 
     */
    public function removeAll($force = false, $persistent = null);
    
    /**
     * removes all entries with the given tag(s) from cache
     * 
     * @param array|string $tag multiple tags used to find the entries to remove
     * @param bool $force if false (default), only fresh, non persistent entries will be removed 
     * @param type $persistent only used if force=true. if true, only persistent entries will be removed, if false only non-persistent entries will be removed, if null(default) both persistent and non persistent entries will be removed
     * @return bool if the request was successful 
     */
    public function removeByTag($tag, $force = false, $persistent = null);
    
    /**
     * outdates an entry in cache (sets fresh_until to the past)
     * 
     * @param string $key the key of the entry to outdate
     * @param bool $force if false (default), the entry will only get outdated if it is fresh and non persistent 
     * @param bool|null $persistent only used if force=true. if true, the entry gets outdated only if it is persistent, if false only if it is non-persistent, if null (default) if gets outdated regardless of the persistent state
     * @return bool if the request was successful 
     */
    public function outdate($key, $force = false, $persistent = null);
    
    /**
     * outdates all entries in cache (sets fresh_until to the past)
     * 
     * @param bool $force if false (default), only fresh, non persistent entries will be outdated 
     * @param bool|null $persistent only used if force=true. if true, only persistent entries get outdated, if false only non-persistent entries get outdated, if null(default) both persistent and non persistent entries get outdated
     * @return bool if the request was successful 
     */
    public function outdateAll($force = false, $persistent = null);
    
    /**
     * outdates all entries with the given tag(s) in cache (sets fresh_until to the past)
     * 
     * @param array|string $tag multiple tags used to find the entries to outdate
     * @param bool $force if false (default), only fresh, non persistent entries will be outdated 
     * @param bool|null $persistent only used if force=true. if true, only persistent entries get outdated, if false only non-persistent entries get outdated, if null(default) both persistent and non persistent entries get outdated
     * @return bool if the request was successful 
     */
    public function outdateByTag($tag, $force = false, $persistent = null);
    
    /**
     * sets the connection class
     * 
     * @param IConnection $connection an ConnectionInterface instance
     */
    public function setConnection(ConnectionInterface $connection);
    
    /**
     * gets the connection
     * 
     * @return IConnection the connection instance
     */
    public function getConnection();


}

