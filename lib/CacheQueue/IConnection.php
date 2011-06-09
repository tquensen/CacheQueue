<?php
namespace CacheQueue;

interface IConnection
{
    public function __construct($config);
    
    public function get($key);
    
    public function queue($key, $task, $params, $freshUntil);
    
    public function getJob();
    
    public function setData($key, $data);
    
    public function getQueueCount();
    
    public function cleanup($maxFreshUntil);
}
