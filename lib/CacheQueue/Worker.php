<?php
namespace CacheQueue;

class Worker implements IWorker
{
    private $connection;
    private $tasks = array();
    
    private $logger = null;
    
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

        $taskData = (array) $this->tasks[$task];
        $taskClass = $taskData[0];
        $taskMethod = !empty($taskData[1]) ? $taskData[1] : 'execute';
        $taskConfig = !empty($taskData[2]) ? $taskData[2] : array();
        
        if (!class_exists($taskClass)) {
            $taskFile = str_replace('\\', DIRECTORY_SEPARATOR, trim($taskClass, '\\')).'.php';
            require_once($taskFile);
        }

        if (!class_exists($taskClass)) {
            throw new \Exception('class '.$taskClass.' not found.');
        }
        if (!method_exists($taskClass, $taskMethod)) {
            throw new \Exception('method '.$taskMethod.' in in class '.$taskClass.' not found.');
        }

        $task = new $taskClass;     
//        if (!$task instanceof \CacheQueue\ITask) {
//            throw new \Exception('class '.$taskClass.' does not implement \\CacheQueue\\ITask.');
//        }

        $result = $task->$taskMethod($params, $taskConfig, $job, $this);

        if ($result !== null) {
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
    
    public function setLogger(ILogger $logger)
    {
         $this->logger = $logger;
    }
    
    public function getLogger()
    {
        return $this->logger;
    }
    
    public function setConnection(IConnection $connection)
    {
         $this->connection = $connection;
    }
    
    public function getConnection()
    {
        return $this->connection;
    }

}
