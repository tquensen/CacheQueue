<?php
namespace CacheQueue\Logger;

interface LoggerInterface
{
    const LOG_NONE = 0;
    const LOG_DEBUG = 1;
    const LOG_NOTICE = 2;
    const LOG_ERROR = 4;
    const LOG_ALL = 7;
    
    public function __construct($config = array());
    
    public function logDebug($text);
    
    public function logNotice($text);
    
    public function logError($text);
    
    public function logException($e);
}
