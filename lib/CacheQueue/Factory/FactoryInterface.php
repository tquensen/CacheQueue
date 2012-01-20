<?php
namespace CacheQueue\Factory;
use CacheQueue\Client\ClientInterface,
    CacheQueue\Worker\WorkerInterface,
    CacheQueue\Connection\ConnectionInterface,
    CacheQueue\Logger\LoggerInterface;
        
interface FactoryInterface
{

    public function __construct($config);
    
    /**
     * initializes or gets the client
     * 
     * @return ClientInterface the client instance 
     */
    public function getClient();
    
    /**
     * initializes or gets the worker
     * 
     * @return WorkerInterface the worker instance 
     */
    public function getWorker();
    
    /**
     * initializes or gets the connection
     * 
     * @return ConnectionInterface the connection instance 
     */
    public function getConnection();
    
    /**
     * initializes or gets the logger
     * 
     * @return LoggerInterface the logger instance 
     */
    public function getLogger();
}
