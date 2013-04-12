<?php
namespace CacheQueue\Logger;

class Sentry implements LoggerInterface
{
    private $sentryDSN = null;
    private $options = array();
    private $showPid = false;
    private $logLevel = 0;
    
    private $ravenClient = null;
    private $errorHandler = null;
    
    public function __construct($config = array())
    {
        if (!empty($config['ravenAutoloaderFile']) && !class_exists('\\Raven_Autoloader')) {
            require_once($config['ravenAutoloaderFile']);
            \Raven_Autoloader::register();
        }
        $this->sentryDSN = $config['sentryDSN'];
        $this->options = $config['options'];
        $this->showPid = !empty($config['showPid']);
        $this->logLevel = !empty($config['logLevel']) ? $config['logLevel'] : self::LOG_NONE;
        
        if (!empty($config['registerErrorHandler']) || !empty($config['registerExceptionHandler'])) {
            $this->initClient();
            
            if (!empty($config['registerErrorHandler']) || !empty($config['registerExceptionHandler'])) {
                $this->errorHandler = new Raven_ErrorHandler($this->ravenClient);
                if (!empty($config['registerErrorHandler'])) {
                    set_error_handler(array($this->errorHandler, 'handleError'));
                }
                if (!empty($config['registerExceptionHandler'])) {
                    set_exception_handler(array($this->errorHandler, 'handleException'));
                }
            }
        }
    }

    public function logException($e)
    {
        if ($this->logLevel & self::LOG_ERROR) {
            $this->doLogException($e, \Raven_Client::ERROR);
        }
    }
    
    public function logError($text)
    {
        if ($this->logLevel & self::LOG_ERROR) {
            $this->doLog($text, \Raven_Client::ERROR);
        }
    }

    public function logNotice($text)
    {
        if ($this->logLevel & self::LOG_NOTICE) {
            $this->doLog($text, \Raven_Client::INFO);
        }
    }
    
    public function logDebug($text)
    {
        if ($this->logLevel & self::LOG_DEBUG) {
            $this->doLog($text, \Raven_Client::DEBUG);
        }
    }
    
    public function getRavenClient()
    {
        if (empty($this->ravenClient)) {
            $this->initClient();
        }
        return $this->ravenClient;
    }
    
    public function getErrorHandler()
    {
        return $this->errorHandler;
    }
    
    private function initClient()
    {
        $this->ravenClient = new \Raven_Client($this->sentryDSN, $this->options);
    }
    
    private function doLog($message, $level, $e)
    {
        if (empty($this->ravenClient)) {
            $this->initClient();
        }
        
        $this->ravenClient->captureMessage(($this->showPid ? 'PID '.getmypid().' | ' : '').$message, array(), $level);
    }
    
    private function doLogException($e, $level)
    {
        if (empty($this->ravenClient)) {
            $this->initClient();
        }
        
        $this->ravenClient->captureException($e, array('level' => $level));
    }
    
}

