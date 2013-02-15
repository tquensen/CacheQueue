<?php
namespace CacheQueue\Task;
use CacheQueue\Exception\Exception;

class Piwik
{
    private $piwikUrl = null;
    private $idSite = 'all';
    private $token = null;
    
    public function doAction($params, $config, $job, $worker)
    {
        if ((empty($config['token']) && empty($params['token'])) || empty($config['piwikUrl'])) {
            throw new \Exception('Config parameters token and piwikUrl are required!');
        }
        if (empty($params['action']) || empty($params['period']) || empty($params['date'])) {
            throw new \Exception('parameters action, period and date are required!');
        }
        
        $this->piwikUrl = $config['piwikUrl'];
        $this->token = isset($params['token']) ? $params['token'] : $config['token'];
        
        if (!empty($params['idSite'])) {
            $this->idSite = $params['idSite'];
        } elseif (!empty($config['idSite'])) {
            $this->idSite = $config['idSite'];
        }
        
        $requestUrl = rtrim($this->piwikUrl, '/').'/index.php?module=API&method=Actions.'.$params['action'].'&period='.$params['period'].'&date='.$params['date'].'&segment='.(isset($params['segment']) ? $params['segment'] : '').'&format=PHP&idSite='.$this->idSite.'&token_auth='.$this->token;
        
        
        if (!empty($params['parameter'])) {
            foreach ((array) $params['parameter'] as $k => $v) {
                $requestUrl .= '&'.(is_numeric($k) ? $v : $k.'='.$v);
            }
        }
        
        $response = @file_get_contents($requestUrl);
        $responseData = @unserialize($response);
        
        if ($responseData) {
            if (is_array($responseData) && isset($responseData['result']) && $responseData['result'] == 'error') {
                throw new Exception('Piwik request failed: '.$responseData['message']);
            }
            if ($logger = $worker->getLogger()) {
                $logger->logDebug('Requesting Piwik URL '.$requestUrl.' successful.');
            }
            return !empty($params['returnSingle']) && is_array($responseData) ? reset($responseData) : $responseData;
        } else {
            throw new Exception('Requesting Piwik URL '.$requestUrl.' failed.');
            return false;
        }
        
    }
    
}
