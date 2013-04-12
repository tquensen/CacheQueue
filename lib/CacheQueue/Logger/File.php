<?php
namespace CacheQueue\Logger;

class File implements LoggerInterface
{
    private $file = null;
    private $showPid = false;
    private $logLevel = 0;
    
    public function __construct($config = array())
    {
        $this->file = $config['file'];
        $this->showPid = !empty($config['showPid']);
        $this->logLevel = !empty($config['logLevel']) ? $config['logLevel'] : self::LOG_NONE;
    }

    public function logException($e)
    {
        if ($this->logLevel & self::LOG_ERROR) {
            $this->doLog((string) $e, 'EXCEPTION');
        }
    }
    
    public function logError($text)
    {
        if ($this->logLevel & self::LOG_ERROR) {
            $this->doLog($text, 'ERROR   ');
        }
    }

    public function logNotice($text)
    {
        if ($this->logLevel & self::LOG_NOTICE) {
            $this->doLog($text, 'NOTICE  ');
        }
    }
    
    public function logDebug($text)
    {
        if ($this->logLevel & self::LOG_DEBUG) {
            $this->doLog($text, 'DEBUG  ');
        }
    }
    
    private function doLog($message, $level)
    {
        file_put_contents($this->file, date('[Y-m-d H.i:s] ').($this->showPid ? 'PID '.getmypid().' | ' : '').$level.' '.$message."\n", \FILE_APPEND | \LOCK_EX);
    }

}

