<?php
namespace CacheQueue\Task;
use CacheQueue\Exception\Exception;

/**
 * MailChimp API tasks
 *  
 */
class MailChimp
{
    
    public function execute($params, $config, $job, $worker)
    {
        if (!empty($config['MCAPIFile']) && !class_exists('\\MCAPI')) {
            require_once($config['MCAPIFile']);
        }
        
        if (empty($params['method'])) {
            throw new \Exception('parameter method is required!');
        }
        
        if (empty($config['apiKey']) && empty($params['apiKey'])) {
            throw new \Exception('config parameter apiKey is required!');
        }
        
        $timeout = isset($params['timeout']) ? $params['timeout'] : (isset($config['timeout']) ? $config['timeout'] : 300);
        $secure = isset($params['secure']) ? $params['secure'] : (isset($config['secure']) ? $config['secure'] : false);
        
        
        $mc = new \MCAPI(isset($params['apiKey']) ? $params['apiKey'] : $config['apiKey'], $secure);
        $mc->setTimeout($timeout);
        
        $response = $mc->callServer($params['method'], isset($params['parameter']) ? $params['parameter'] : array());
        
        if ($response) {
            return $response;
        } elseif($mc->errorCode) {
            throw new Exception('MailChimp API request '.$params['method']. 'failed with error '.$mc->errorCode.': '.$mc->errorMessage);
        } else {
            throw new Exception('MailChimp API request '.$params['method']. 'failed with unknown error');
        }
        
    }
    
}
