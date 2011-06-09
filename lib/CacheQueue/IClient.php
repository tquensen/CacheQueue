<?php
namespace CacheQueue;

interface IClient
{

    public function __construct(IConnection $connection);

    public function get($key);

    public function queue($key, $task, $params, $freshUntil);

    public function getOrQueue($key, $task, $params, $freshUntil);
    
}

