<?php
namespace CacheQueue;

interface IWorker
{
    public function __construct(IConnection $connection, $tasks, $config = array());
    
    /**
     * gets an entry from queue, runs the associated task and updates the value
     * throws an exception on error
     * 
     * @return bool returns true if the task was processed 
     */
    public function work();
    
    /**
     * gets a queued entry and removes it from queue
     * 
     * @return array|bool the job data or false if no job was found 
     */
    public function getJob();
    
    /**
     * sets the connection class
     * 
     * @param IConnection $connection an IConnection instance
     */
    public function setConnection(IConnection $connection);
    
    /**
     * gets the connection
     * 
     * @return IConnection the connection instance
     */
    public function getConnection();
    
    /**
     * sets a logger which can be accessed by the tasks
     * 
     * @param ILogger $logger an ILogger instance
     */
    public function setLogger(ILogger $logger);
    
    /**
     * gets the logger or null if no logger was set
     * 
     * @return ILogger the logger instance
     */
    public function getLogger();
}
