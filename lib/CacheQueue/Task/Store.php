<?php
namespace CacheQueue\Task;

class Store implements \CacheQueue\ITask
{
    public function execute($params, $job)
    {
        return $params;
    }
}
