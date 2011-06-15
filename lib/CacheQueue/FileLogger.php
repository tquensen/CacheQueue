<?php

namespace CacheQueue;

class FileLogger implements ILogger
{
    private $file = null;
    
    public function __construct($config = array())
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
        file_put_contents($this->file, date('[Y-m-d H.i:s] ').$level.' '.$message."\n", FILE_APPEND);
    }

}

