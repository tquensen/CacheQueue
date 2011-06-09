<?php
namespace CacheQueue;

interface IWorker
{
    public function __construct(IConnection $connection, $tasks);
    
    public function work();
    
    public function getJob();
    
    public function setData($key, $data);
}
