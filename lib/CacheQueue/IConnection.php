<?php
namespace CacheQueue;

interface IConnection
{
    public function __construct($config);
    
    public function get($key);
    
    public function set($key, $data, $freshFor, $force = false);

    public function queue($key, $task, $params, $freshFor, $force = false);
    
    public function getJob();
    
    public function setData($key, $data);
    
    public function getQueueCount();
    
    public function remove($key, $force = false);
    
    public function removePersistent();
    
    public function clear($outdatedFor = 0, $force = false);
}
