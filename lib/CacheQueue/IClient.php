<?php
namespace CacheQueue;

interface IClient
{

    public function __construct(IConnection $connection);

    /**
     * get a cached entry
     * 
     * @param string $key the key to get
     * @param bool $onlyValue true to return only the value, false to return the result array
     * @return mixed the value or the result array (depending on $onlyValue) or false if not found 
     */
    public function get($key, $onlyValue = true);

    /**
     * save cache data
     * 
     * @param string $key the key to save the data for
     * @param mixed $data the data to be saved
     * @param int|bool $freshFor number of seconds that the data is fresh or true to store as persistent
     * @param bool $force true to force the save even if the data is still fresh
     * @return bool if the save was sucessful 
     */
    public function set($key, $data, $freshFor, $force = false);

    /**
     * add a queue entry 
     * 
     * @param string $key the key to save the data for
     * @param string $task the task to run
     * @param mixed $params parameters for the task
     * @param int|bool $freshFor number of seconds that the data is fresh or true to store as persistent
     * @param bool $force true to force the queue even if the data is still fresh
     * @return bool if the queue was sucessful 
     */
    public function queue($key, $task, $params, $freshFor, $force = false);

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
    public function getOrSet($key, $callback, $params, $freshFor, $force = false);

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
    public function getOrQueue($key, $task, $params, $freshFor, $force = false);

}

