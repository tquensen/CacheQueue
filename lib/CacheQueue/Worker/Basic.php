<?php
namespace CacheQueue\Worker;
use CacheQueue\Connection\ConnectionInterface,
    CacheQueue\Logger\LoggerInterface,
    CacheQueue\Exception\Exception;

class Basic implements WorkerInterface
{
    private $connection;
    private $tasks = array();
    
    private $logger = null;
    
    public function __construct(ConnectionInterface $connection, $tasks, $config = array())
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
        $freshUntil = $job['persistent'] ? true : $job['fresh_until'];
        $temp = !empty($job['temp']);
        
        if (empty($this->tasks[$task])) {
            throw new Exception('invalid task '.$task.'.');
        }

        $taskData = (array) $this->tasks[$task];
        $taskClass = $taskData[0];
        $taskMethod = !empty($taskData[1]) ? $taskData[1] : 'execute';
        $taskConfig = !empty($taskData[2]) ? $taskData[2] : array();
        
        if (!class_exists($taskClass)) {
            $taskFile = str_replace('\\', \DIRECTORY_SEPARATOR, trim($taskClass, '\\')).'.php';
            require_once($taskFile);
        }

        if (!class_exists($taskClass)) {
            throw new Exception('class '.$taskClass.' not found.');
        }
        if (!method_exists($taskClass, $taskMethod)) {
            throw new Exception('method '.$taskMethod.' in in class '.$taskClass.' not found.');
        }

        $task = new $taskClass;     
//        if (!$task instanceof \CacheQueue\ITask) {
//            throw new \Exception('class '.$taskClass.' does not implement \\CacheQueue\\ITask.');
//        }

        $result = $task->$taskMethod($params, $taskConfig, $job, $this);

        if ($temp) {
            $this->connection->remove($job['key'], true);
        } elseif ($result !== null) {
            $this->connection->set($job['key'], $result, $freshUntil, false, $job['tags']);
        }

        return true;
    }

    public function getJob()
    {
        return $this->connection->getJob();
    }
    
    public function setLogger(LoggerInterface $logger)
    {
         $this->logger = $logger;
    }
    
    public function getLogger()
    {
        return $this->logger;
    }
    
    public function setConnection(ConnectionInterface $connection)
    {
         $this->connection = $connection;
    }
    
    public function getConnection()
    {
        return $this->connection;
    }

}