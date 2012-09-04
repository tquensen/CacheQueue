<?php
namespace CacheQueue\Factory;

use CacheQueue\Client\ClientInterface,
    CacheQueue\Worker\WorkerInterface,
    CacheQueue\Connection\ConnectionInterface,
    CacheQueue\Logger\LoggerInterface;
        
class Factory implements FactoryInterface
{
    private $config;
    private $client;
    private $worker;
    private $logger;
    private $connection;
    
    public function __construct($config)
    {
        $this->config = $config;
        
        require_once('CacheQueue/Exception/Exception.php');
        require_once('CacheQueue/Connection/ConnectionInterface.php');
        require_once('CacheQueue/Client/ClientInterface.php');
        require_once('CacheQueue/Logger/LoggerInterface.php');
        require_once('CacheQueue/Worker/WorkerInterface.php');
    }

    public function getClient()
    {
        if (!$this->client) {
            $clientClass = $this->config['classes']['client'];
            if (!class_exists($clientClass)) {
                $clientFile = str_replace('\\', \DIRECTORY_SEPARATOR, trim($clientClass, '\\')).'.php';
                require_once($clientFile);
            }
            $this->client = new $clientClass($this->getConnection());          
            $this->client->setWorker($this->getWorker());
        }
        return $this->client;
    }

    public function getConnection()
    {
        if (!$this->connection) {
            $connectionClass = $this->config['classes']['connection'];
            if (!class_exists($connectionClass)) {
                $connectionFile = str_replace('\\', \DIRECTORY_SEPARATOR, trim($connectionClass, '\\')).'.php';
                require_once($connectionFile);
            }
            $this->connection = new $connectionClass($this->config['connection']);
        }
        return $this->connection;
    }

    public function getLogger()
    {
        if (!$this->logger) {
            $loggerClass = $this->config['classes']['logger'];
            if (!class_exists($loggerClass)) {
                $loggerFile = str_replace('\\', \DIRECTORY_SEPARATOR, trim($loggerClass, '\\')).'.php';
                require_once($loggerFile);
            }
            $this->logger = new $loggerClass($this->config['logger']);
        }
        return $this->logger;
    }

    public function getWorker()
    {
        if (!$this->worker) {
            $workerClass = $this->config['classes']['worker'];
            if (!class_exists($workerClass)) {
                $workerFile = str_replace('\\', \DIRECTORY_SEPARATOR, trim($workerClass, '\\')).'.php';
                require_once($workerFile);
            }
            $this->worker = new $workerClass($this->getConnection(), $this->config['tasks']);
            $this->worker->setLogger($this->getLogger());
            
        }
        return $this->worker;
    }

}
