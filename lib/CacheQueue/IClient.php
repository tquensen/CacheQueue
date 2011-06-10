<?php
namespace CacheQueue;

interface IClient
{

    public function __construct(IConnection $connection);

    public function get($key);

    public function set($key, $data, $freshFor, $force = false);

    public function queue($key, $task, $params, $freshFor, $force = false);

    public function getOrSet($key, $callback, $params, $freshFor, $force = false);

    public function getOrQueue($key, $task, $params, $freshFor, $force = false);

}

