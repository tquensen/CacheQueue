<?php
namespace CacheQueue;

interface IWorker
{
    public function __construct(IConnection $connection, $tasks);
    
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
     * updates the data for a given cache entry
     * 
     * @param string $key the key to save the data for
     * @param mixed $data the data
     * @return bool if save was successful 
     */
    public function setData($key, $data);
    
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
