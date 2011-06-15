<?php
namespace CacheQueue\Task;

/**
 * the 'store' task simply caches the submitted params as the data
 * 
 * if a task returns null/nothing, the data won't be updated.
 * if you want do remove/clear the data, you should return false
 * 
 * throw a \CacheQueue\Exception for non-critical errors (this will be logged)
 * any other exceptions will terminate the (default) worker process 
 * in any case, when throwing an exception, the cache entry is removed from queue and wont get updated
 */
class Store
{
    public function execute($params, $config, $job, $worker)
    {
        return $params;
    }
}
