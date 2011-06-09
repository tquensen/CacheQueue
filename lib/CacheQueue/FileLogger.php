<?php

namespace CacheQueue;

class FileLogger implements ILogger
{
    private $file = null;
    
    public function __construct($config)
    {
        $this->file = $config['file'];
    }

    public function logError($text)
    {
        $this->doLog($text, 'ERROR   ');
    }

    public function logNotice($text)
    {
        $this->doLog($text, 'NOTICE  ');
    }
    
    private function doLog($message, $level)
    {
        file_put_contents($this->file, $level.' '.$message."\n", FILE_APPEND);
    }

}

