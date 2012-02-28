<?php
namespace CacheQueue\Task;
use CacheQueue\Exception\Exception;

class Analytics
{
    private $client = null;
    private $service = null;
    private $token = null;
    private $tokenCacheKey = null;
    
    private function initClient($clientKey, $clientSecret, $refresh_token, $connection, $logger)
    {
        require_once 'Google/apiClient.php';
        require_once 'Google/contrib/apiAnalyticsService.php';

        $client = new \apiClient();
        $client->setApplicationName("t3n.de");
        $client->setClientId('240342825658.apps.googleusercontent.com');
        $client->setClientSecret('cPSMZAKd0U5raQ4K6r42GyTt');
        
        $service = new \apiAnalyticsService($client);
        
        $this->tokenCacheKey = 'analytics_token_'.md5($clientKey.$clientSecret.$refresh_token);
        
        $token = $this->getToken($refresh_token, $client, $connection, $logger);
        $client->setAccessToken($token);
        
        $this->token = $token;
        $this->client = $client;
        $this->service = $service;
        return $this->service;
    }
    
    public function getPageviews($params, $config, $job, $worker)
    {
        if (empty($config['clientKey']) || empty($config['clientSecret'])) {
            throw new \Exception('Config parameters clientKey and clientSecret are required!');
        }
        
        if (empty($params['pagePath']) || empty($params['profileId']) || empty($params['refreshToken'])) {
            throw new \Exception('parameters parameters pagePath, profileId and refreshToken are required!');
        }
        
//        $url = str_replace(array('https://', 'http://'), '', $params['pagePath']);
//        $tmp = explode('/', $url, 2);
//        $path = '/'. (isset($tmp[1]) ? $tmp[1] : '');
        
        
        $path = $params['pagePath'];
        $hostStr = !empty($params['hostname']) ? 'ga:hostname=='.$params['hostname'].';' : '';
        
        $op = !empty($params['operator']) ? $params['operator'] : '==';
        
        $service = $this->initClient($config['clientKey'], $config['clientSecret'], $params['refreshToken'], $worker->getConnection(), $worker->getLogger());
        
        $dateFrom = '2005-01-01';
        $dateTo = date('Y-m-d');
        
        $tries = 3;
        while(true) {
            try {    
                $data = $service->data_ga->get('ga:'.$params['profileId'], $dateFrom, $dateTo, 'ga:pageviews', array(
                    'dimensions' => 'ga:pagePath',
                    'sort' => '-ga:pageviews',
                    'max-results' => 50,
                    'filters' => $hostStr.'ga:pagePath'.$op.$path
                ));
                break;
            } catch (\Exception $e) {
                if (!--$tries) {
                    throw new Exception('Api-Error:' .$e->getMessage(), $e->getCode(), $e);
                }
                usleep(50000);
            }
        };
        
        
        $count = $data['totalsForAllResults']['ga:pageviews'];
        
        if ($logger = $worker->getLogger()) {
            $logger->logDebug('Analytics Pageviews: '.(!empty($params['hostname']) ? 'Host='.$params['hostname'] . ' | ' : '').'Path='.$path.' | COUNT='.$count);
        }

        
        return (int) $count;
    }
    
    public function getTopUrls($params, $config, $job, $worker)
    {
        if (empty($config['clientKey']) || empty($config['clientSecret'])) {
            throw new \Exception('Config parameters clientKey and clientSecret are required!');
        }
        
        if (empty($params['profileId']) || empty($params['refreshToken'])) {
            throw new \Exception('parameters, profileId and refreshToken are required!');
        }
        
        if (empty($params['pathPrefix'])) {
            $params['pathPrefix'] = '/';
        }
        $hostStr = !empty($params['hostname']) ? 'ga:hostname=='.$params['hostname'].';' : '';
        
        if (!empty($params['count'])) {
            $limit = $params['count'];
        } else {
            $limit = !empty($config['count']) ? $config['count'] : 10;
        }
         
        $service = $this->initClient($config['clientKey'], $config['clientSecret'], $params['refreshToken'], $worker->getConnection(), $worker->getLogger());
        
        $dateFrom = !empty($params['dateFrom']) ? $params['dateFrom'] : '2005-01-01';
        $dateTo = !empty($params['dateTo']) ? $params['dateTo'] : date('Y-m-d');
        
        $tries = 3;
        while(true) {
            try {  
                $data = $service->data_ga->get('ga:'.$params['profileId'], $dateFrom, $dateTo, 'ga:pageviews', array(
                    'dimensions' => 'ga:pagePath',
                    'sort' => '-ga:pageviews',
                    'max-results' => $limit,
                    'filters' => $hostStr.'ga:pagePath=~^'.$params['pathPrefix']
                ));
                break;
            } catch (\Exception $e) {
                if (!--$tries) {
                    throw new Exception('Api-Error:' .$e->getMessage(), $e->getCode(), $e);
                }
                usleep(50000);
            }
        }
        
        $topUrlsTmp = array();

        foreach ($data['rows'] as $row) {
            $title = (string)$row[0];
            $count = (int)$row[1];

            $topUrlsTmp[$title] = $count;
        }
        
        arsort($topUrlsTmp);
        
        $topUrls = array();
        foreach ($topUrlsTmp as $k=>$v) {
            $topUrls[] = array(
                'url' => $k,
                'pageviews' => $v
            );
        }
        
        if ($logger = $worker->getLogger()) {
            $logger->logDebug('Analytics: TopUrls: '.(!empty($params['hostname']) ? 'Host='.$params['hostname'] . ' | ' : '').'Path='.$params['pathPrefix'].' | COUNT='.count($topUrls));
        }

        
        return $topUrls;
    }
    
    public function getTopKeywords($params, $config, $job, $worker)
    {
        if (empty($config['clientKey']) || empty($config['clientSecret'])) {
            throw new \Exception('Config parameters clientKey and clientSecret are required!');
        }
        
        if (empty($params['profileId']) || empty($params['refreshToken'])) {
            throw new \Exception('parameters, profileId and refreshToken are required!');
        }
        
        if (empty($params['pathPrefix'])) {
            $params['pathPrefix'] = '/';
        }
        $hostStr = !empty($params['hostname']) ? 'ga:hostname=='.$params['hostname'].';' : '';
        
        if (!empty($params['count'])) {
            $limit = $params['count'];
        } else {
            $limit = !empty($config['count']) ? $config['count'] : 10;
        }
         
        $service = $this->initClient($config['clientKey'], $config['clientSecret'], $params['refreshToken'], $worker->getConnection(), $worker->getLogger());
        
        $dateFrom = !empty($params['dateFrom']) ? $params['dateFrom'] : date('Y-m-d',  mktime(0, 0, 0, date('m')-1, date('d'), date('Y')));
        $dateTo = !empty($params['dateTo']) ? $params['dateTo'] : date('Y-m-d');

        $tries = 3;
        while(true) {
            try {  
                $data = $service->data_ga->get('ga:'.$params['profileId'], $dateFrom, $dateTo, 'ga:pageviews', array(
                    'dimensions' => 'ga:keyword',
                    'sort' => '-ga:pageviews',
                    'max-results' => $limit,
                    'filters' => $hostStr.'ga:pagePath=~^'.$params['pathPrefix']
                ));
                break;
            } catch (\Exception $e) {
                if (!--$tries) {
                    throw new Exception('Api-Error:' .$e->getMessage(), $e->getCode(), $e);
                }
                usleep(50000);
            }
        }
        
        $topKeywordsTmp = array();

        foreach($data['rows'] as $row) {
            $title = (string)$row[0];
            $count = (int)$row[1];

            $topKeywordsTmp[$title] = $count;
        }
        
        arsort($topKeywordsTmp);
        
        $topKeywords = array();
        foreach ($topKeywordsTmp as $k=>$v) {
            if (!$k || $k == '(not set)' || $k == '(not provided)') {
                continue;
            }
            $topKeywords[] = array(
                'keyword' => $k,
                'pageviews' => $v
            );
        }
        
        if ($logger = $worker->getLogger()) {
            $logger->logDebug('Analytics: TopKeywords: '.(!empty($params['hostname']) ? 'Host='.$params['hostname'] . ' | ' : '').'Path='.$params['pathPrefix'].' | COUNT='.count($topKeywords));
        }

        
        return $topKeywords;
    }
    
    private function getToken($refresh_token, $client, $connection, $logger)
    {
        $cachedTokenData = $connection->get($this->tokenCacheKey);
        if (!$cachedTokenData || !$cachedTokenData['is_fresh']) {
            $lockKey = $connection->obtainLock($this->tokenCacheKey, 5);
            if ($lockKey) {
                $cachedTokenData = $connection->get($this->tokenCacheKey);
                if (!$cachedTokenData || !$cachedTokenData['is_fresh']) {
                    try {
                        if ($logger) {
                            $logger->logDebug('Analytics: refreshing access token');
                        }
                                            
                        $tries = 3;
                        while(true) {
                            try {
                                $client->refreshToken($refresh_token);
                                break;
                            } catch (\Exception $e) {
                                if (!--$tries) {
                                    throw new Exception('Api-Error:' .$e->getMessage(), $e->getCode(), $e);
                                }
                                usleep(50000);
                            }
                        }
                        
                        $tmpToken = $client->getAccessToken();     
                        $tmp = json_decode($tmpToken, true);
                        $tmp['refresh_token'] = $refresh_token;
                        $token = json_encode($tmp);
                        $connection->set($this->tokenCacheKey, $token, $tmp['created'] + $tmp['expires_in'] - 60 - time(), true);
                        $connection->releaseLock($this->tokenCacheKey, $lockKey);
                        return $token;
                    } catch (\Exception $e) {
                        $connection->releaseLock($this->tokenCacheKey, $lockKey);
                        throw $e;
                    }
                }
                $connection->releaseLock($this->tokenCacheKey, $lockKey);
            } else {
                throw new Exception('could not generate new access token');
            }
        } 
        
        return $cachedTokenData['data'];
    }
}
