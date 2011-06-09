<?php
namespace CacheQueue\Task;

/**
 * the 'store' task simply caches the submitted params as the data
 * 
 * if a task returns null/nothing, the data won't be updated.
 * if you want do remove/clear the data, you should return false
 * 
 * you should throw an exception if an error occurs.
 */
class Store
{
    public function execute($params, $job)
    {
        return $params;
    }
}
