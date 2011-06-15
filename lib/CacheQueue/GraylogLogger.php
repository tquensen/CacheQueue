<?php

namespace CacheQueue;

class GraylogLogger implements ILogger
{
    private $graylogHostname = null;
    private $graylogPort = null;
    private $host = '';
    
    public function __construct($config = array())
    {
        require_once($config['gelfFile']);
        $this->graylogHostname = $config['graylogHostname'];
        $this->graylogPort = $config['graylogPort'];
        $this->host = $config['host'];
        $this->facility = !empty($config['facility']) ? $config['facility'] : 'CacheQueue';
    }

    public function logError($text)
    {
        $this->doLog($text, 3);
    }

    public function logNotice($text)
    {
        $this->doLog($text, 5);
    }
    
    public function logDebug($text)
    {
        $this->doLog($text, 7);
    }
    
    private function doLog($message, $level)
    {
        $gelf = new \GELFMessage($this->graylogHostname, $this->graylogPort);

        $gelf->setShortMessage($message);
        $gelf->setHost($this->host);
        $gelf->setTimestamp(time());
        $gelf->setLevel($level);
        $gelf->setFacility($this->facility);
        $gelf->send();
    }

}

