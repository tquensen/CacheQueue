<?php
namespace CacheQueue\Logger;

interface LoggerInterface
{
    public function __construct($config = array());
    
    public function logDebug($text);
    
    public function logNotice($text);
    
    public function logError($text);
}
