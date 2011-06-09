<?php
namespace CacheQueue;

interface ITask
{ 
    public function execute($params, $job);
}
