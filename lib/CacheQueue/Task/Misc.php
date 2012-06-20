<?php
namespace CacheQueue\Task;

/**
 * the 'misc' class contains various simple tasks
 * 
 * if a task returns null/nothing, the data won't be updated.
 * if you want do remove/clear the data, you should return false
 * 
 * throw a \CacheQueue\Exception\Exception for non-critical errors (this will be logged)
 * any other exceptions will terminate the (default) worker process 
 * in any case, when throwing an exception, the cache entry is removed from queue and wont get updated
 */
class Misc
{
    /**
     * the 'store' task simply caches the submitted params as the data
     */
    public function store($params, $config, $job, $worker)
    {
        return $params;
    }
    
    /**
     * reads and stores the content of a url
     */
    public function loadUrl($params, $config, $job, $worker)
    {
        if (empty($params['url'])) {
            throw new \Exception('Parameters url is required!');
        }
        
        if (!empty($params['context'])) {
            $context = @stream_context_create($params['context']);
            $result = @file_get_contents($params['url'], null, $context);
        } else {
            $result = @file_get_contents($params['url'], null, $context);
        }
        
        if ($result === false) {
            if (!empty($params['disableErrorLog']) && $logger = $worker->getLogger()) {
                $logger->logError('loadUrl: failed for URL '.$params['url']);
            }
            return;
        }
        
        if (!empty($params['format']) && $params['format'] == 'json') {
            $result = @json_decode($result, true);
            if ($result === null) {
                if (!empty($params['disableErrorLog']) && $logger = $worker->getLogger()) {
                    $logger->logError('loadUrl: failed to convert data to json for URL '.$params['url']);
                }
                return;
            }
        }
        
        if ($logger = $worker->getLogger()) {
            $logger->logDebug('loadUrl: successful for URL '.$params['url']);
        }
        return $result;
    }
}
