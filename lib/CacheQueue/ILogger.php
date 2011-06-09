<?php
namespace CacheQueue;

interface ILogger
{
    public function __construct($config);
    
    public function logNotice($text);
    
    public function logError($text);
}
