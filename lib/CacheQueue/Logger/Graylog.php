<?php
namespace CacheQueue\Logger;

class Graylog implements LoggerInterface
{
    private $graylogHostname = null;
    private $graylogPort = null;
    private $host = '';
    private $showPid = false;
    private $logLevel = 0;
    
    public function __construct($config = array())
    {
        if (!empty($config['gelfFile']) && !class_exists('\\GELFMessage')) {
            require_once($config['gelfFile']);
        }
        $this->graylogHostname = $config['graylogHostname'];
        $this->graylogPort = $config['graylogPort'];
        $this->host = $config['host'];
        $this->facility = !empty($config['facility']) ? $config['facility'] : 'CacheQueue';
        $this->showPid = !empty($config['showPid']);
        $this->logLevel = !empty($config['logLevel']) ? $config['logLevel'] : self::LOG_NONE;
    }
    
    public function logException($e)
    {
        if ($this->logLevel & self::LOG_ERROR) {
            $this->doLog($e->getMessage(), 3, $e);
        }
    }

    public function logError($text)
    {
        if ($this->logLevel & self::LOG_ERROR) {
            $this->doLog($text, 3);
        }
    }

    public function logNotice($text)
    {
        if ($this->logLevel & self::LOG_NOTICE) {
            $this->doLog($text, 5);
        }
    }
    
    public function logDebug($text)
    {
        if ($this->logLevel & self::LOG_DEBUG) {
            $this->doLog($text, 7);
        }
    }
    
    private function doLog($message, $level, $exception = null)
    {
        $gelf = new \GELFMessage($this->graylogHostname, $this->graylogPort);

        $gelf->setShortMessage(($this->showPid ? 'PID '.getmypid().' | ' : '').$message);
        $gelf->setHost($this->host);
        $gelf->setTimestamp(time());
        $gelf->setLevel($level);
        $gelf->setFacility($this->facility);
        if ($exception) {
            $gelf->setFullMessage((string) $exception);
            $gelf->setFile($exception->getFile());
            $gelf->setLine($exception->getLine());
        }
        
        $gelf->send();
    }

}

