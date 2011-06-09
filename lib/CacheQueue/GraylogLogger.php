<?php

namespace CacheQueue;

class GraylogLogger implements ILogger
{
    private $graylogHostname = null;
    private $graylogPort = null;
    private $host = '';
    
    public function __construct($config)
    {
        require_once($config['gelfFile']);
        $this->graylogHostname = $config['graylogHostname'];
        $this->graylogPort = $config['graylogPort'];
        $this->host = $config['host'];
    }

    public function logError($text)
    {
        $this->doLog($text, 3);
    }

    public function logNotice($text)
    {
        $this->doLog($text, 5);
    }
    
    private function doLog($message, $level)
    {
        $gelf = new \GELFMessage($this->graylogHostname, $this->graylogPort);

        $gelf->setShortMessage($message);
        $gelf->setHost('s1.guruwriter.de');
        $gelf->setTimestamp(time());
        $gelf->setLevel($level);
        $gelf->setFacility('clock');
        $gelf->send();
    }

}

