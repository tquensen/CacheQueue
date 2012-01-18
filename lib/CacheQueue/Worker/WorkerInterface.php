<?php
namespace CacheQueue\Worker;
use CacheQueue\Connection\ConnectionInterface,
    CacheQueue\Logger\LoggerInterface,
    CacheQueue\Exception\Exception;

interface WorkerInterface
{
    public function __construct(ConnectionInterface $connection, $tasks, $config = array());
    
    /**
     * gets an entry, runs the associated task and 
     * either deletes the entry if it was temporary,
     * or updated the entries value with the tasks return value (if not returned null)
     * throws an exception on error
     * 
     * @param array $job the job to proceed
     * 
     * @return mixed returns the result/output of the processed task
     */
    public function work($job);
    
    /**
     * gets a queued entry and removes it from queue
     * 
     * @return array|bool the job data or false if no job was found 
     */
    public function getJob();
    
    /**
     * sets the connection class
     * 
     * @param ConnectionInterface $connection an ConnectionInterface instance
     */
    public function setConnection(ConnectionInterface $connection);
    
    /**
     * gets the connection
     * 
     * @return ConnectionInterface the connection instance
     */
    public function getConnection();
    
    /**
     * sets a logger which can be accessed by the tasks
     * 
     * @param LoggerInterface $logger an LoggerInterface instance
     */
    public function setLogger(LoggerInterface $logger);
    
    /**
     * gets the logger or null if no logger was set
     * 
     * @return LoggerInterface the logger instance
     */
    public function getLogger();
}
