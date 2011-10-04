<?php
namespace CacheQueue\Logger;

class File implements LoggerInterface
{
    private $file = null;
    private $showPid = false;
    
    public function __construct($config = array())
    {
        $this->file = $config['file'];
        $this->showPid = !empty($config['showPid']);
    }

    public function logError($text)
    {
        $this->doLog($text, 'ERROR   ');
    }

    public function logNotice($text)
    {
        $this->doLog($text, 'NOTICE  ');
    }
    
    public function logDebug($text)
    {
        $this->doLog($text, 'DEBUG  ');
    }
    
    private function doLog($message, $level)
    {
        file_put_contents($this->file, date('[Y-m-d H.i:s] ').($this->showPid ? 'PID '.getmypid().' | ' : '').$level.' '.$message."\n", \FILE_APPEND);
    }

}
