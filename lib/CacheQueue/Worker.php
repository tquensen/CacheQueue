<?php
namespace CacheQueue;

class Worker implements IWorker
{
    private $connection;
    private $tasks = array();
    
    public function __construct(IConnection $connection, $tasks)
    {
        $this->connection = $connection;
        $this->tasks = $tasks;
    }
    
    public function work()
    {
        if (!$job = $this->getJob()) {
            return false;
        }
        
        $task = $job['task'];
        $params = $job['params'];

        if (empty($this->tasks[$task])) {
            throw new \Exception('invalid task '.$task.'.');
        }

        $taskClass = $this->tasks[$task];
        if (!class_exists($taskClass)) {
            $taskFile = str_replace('\\', DIRECTORY_SEPARATOR, trim($taskClass, '\\')).'.php';
            require_once($taskFile);
        }

        if (!class_exists($taskClass)) {
            throw new \Exception('class '.$taskClass.' not found.');
        }

        $task = new $taskClass;     
        if (!$task instanceof \CacheQueue\ITask) {
            throw new \Exception('class '.$taskClass.' does not implement \\CacheQueue\\ITask.');
        }

        $result = $task->execute($params, $job);

        if ($result !== false) {
            $this->setData($job['key'], $result);
        }

        return true;
    }

    public function getJob()
    {
        return $this->connection->getJob();
    }

    public function setData($key, $data)
    {
        return $this->connection->setData($key, $data);
    }

}
