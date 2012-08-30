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
        $client->setClientId($clientKey);
        $client->setClientSecret($clientSecret);
        
        $service = new \apiAnalyticsService($client);
        
        $this->tokenCacheKey = 'analytics_token_'.md5($clientKey.$clientSecret.$refresh_token);
        
        $token = $this->getToken($refresh_token, $client, $connection, $logger);
        $client->setAccessToken($token);
        
        $this->token = $token;
        $this->client = $client;
        $this->service = $service;
        return $this->service;
    }
    
    public function getMetric($params, $config, $job, $worker)
    {
        $metric = !empty($params['metric']) ? $params['metric'] : false;
        
        if (empty($config['clientKey']) || empty($config['clientSecret'])) {
            throw new \Exception('Config parameters clientKey and clientSecret are required!');
        }
        
        if (!$metric || empty($params['pagePath']) || empty($params['profileId']) || empty($params['refreshToken'])) {
            throw new \Exception('parameters metric, pagePath, profileId and refreshToken are required!');
        }
        
//        $url = str_replace(array('https://', 'http://'), '', $params['pagePath']);
//        $tmp = explode('/', $url, 2);
//        $path = '/'. (isset($tmp[1]) ? $tmp[1] : '');
        
        
        $path = $params['pagePath'];
        $hostStr = !empty($params['hostname']) ? 'ga:hostname=='.$params['hostname'].';' : '';
        
        $op = !empty($params['operator']) ? $params['operator'] : '==';
        
        $dateFrom = !empty($params['dateFrom']) ? $params['dateFrom'] : '2005-01-01';
        $dateTo =  !empty($params['dateTo']) ? $params['dateTo'] : date('Y-m-d');

        $service = $this->initClient($config['clientKey'], $config['clientSecret'], $params['refreshToken'], $worker->getConnection(), $worker->getLogger());
        
        if (!empty($params['bulkCacheTime'])) {
            $bulkCacheKey = 'analytics_cache_'.$metric.'_'.md5($params['profileId'].$dateFrom.$dateTo.$hostStr);
            $bulkCacheData = $worker->getConnection()->get($bulkCacheKey);
            if (!$bulkCacheData || !$bulkCacheData['is_fresh']) {
                $lockKey = $worker->getConnection()->obtainLock($bulkCacheKey, 10, 11);
                if ($lockKey) {
                    $bulkCacheData = $worker->getConnection()->get($bulkCacheKey);
                    if (!$bulkCacheData || !$bulkCacheData['is_fresh']) {
                        try {
                            $startIndex = 1;
                            $bulkCache = array();                            
                            do {
                                $tries = 3;
                                while(true) {
                                    try {    
                                        $extra = array(
                                            'dimensions' => 'ga:hostname,ga:pagePath',
                                            'max-results' => 10000,
                                            'start-index' => $startIndex
                                        );
                                        if (!empty($params['hostname'])) {
                                            $extra['filters'] = 'ga:hostname=='.$params['hostname'];
                                        }
                                        $data = $service->data_ga->get('ga:'.$params['profileId'], $dateFrom, $dateTo, 'ga:'.$metric, $extra);
                                        break;
                                    } catch (\Exception $e) {
                                        if (!--$tries) {
                                            throw new Exception('Api-Error:' .$e->getMessage(), $e->getCode(), $e);
                                        }
                                        usleep(rand(300000,500000)+pow(2,2-$tries)*1000000);
                                    }
                                };
                                $bulkCache = array_merge($bulkCache, $data['rows']);
                            } while($startIndex - 1 + count($data['rows']) < $data['totalResults'] && $startIndex+=10000);
                            $worker->getConnection()->set($bulkCacheKey, $bulkCache, $params['bulkCacheTime'], false, array('analytics', 'bulkcache'));
                            $worker->getConnection()->releaseLock($bulkCacheKey, $lockKey);
                            if ($logger = $worker->getLogger()) {
                                $logger->logDebug('Analytics getMetric ('.$metric.'): created BulkCache with '.count($bulkCache). ' entries');
                            }
                        } catch (\Exception $e) {
                            $worker->getConnection()->releaseLock($bulkCacheKey, $lockKey);
                            throw $e;
                        }
                    } else {
                        $worker->getConnection()->releaseLock($key, $lockKey);
                        $bulkCache = $bulkCacheData['data'];
                    }
                } else {
                    $bulkCache = false;
                }
            } else {
                $bulkCache = $bulkCacheData['data'];
            }
            if (empty($bulkCache)) {
                if ($logger = $worker->getLogger()) {
                    $logger->logError('Analytics getMetric ('.$metric.') from BulkCache: FAILED (no cache available or empty BulkCache)');
                }
                return 0;
            }
            $count = 0;
            switch ($op) {
                case '!=':
                    foreach ($bulkCache as $entry) {
                        if ($entry[1] != $path) $count += (int) $entry[2]; 
                    }
                    break;
                case '=@':
                    foreach ($bulkCache as $entry) {
                        if (strpos($entry[1], $path) !== false) $count += (int) $entry[2]; 
                    }
                    break;
                case '!@':
                    foreach ($bulkCache as $entry) {
                        if (strpos($entry[1], $path) === false) $count += (int) $entry[2]; 
                    }
                    break;
                case '=~':
                    foreach ($bulkCache as $entry) {
                        if (preg_match('/'.str_replace('/','\\/',$path).'/', $entry[1])) $count += (int) $entry[2]; 
                    }
                    break;
                case '!~':
                    foreach ($bulkCache as $entry) {
                        if (!preg_match('/'.str_replace('/','\\/',$path).'/', $entry[1])) $count += (int) $entry[2]; 
                    }
                    break;
                default:
                    foreach ($bulkCache as $entry) {
                        if ($entry[1] == $path) $count += (int) $entry[2]; 
                    }
                    break;
            }
            
            if ($logger = $worker->getLogger()) {
                $logger->logDebug('Analytics getMetric ('.$metric.') from BulkCache: '.(!empty($params['hostname']) ? 'Host='.$params['hostname'] . ' | ' : '').'Path='.$path.' | COUNT='.$count);
            }
        } else {
            $tries = 3;
            while(true) {
                try {    
                    $data = $service->data_ga->get('ga:'.$params['profileId'], $dateFrom, $dateTo, 'ga:'.$metric, array(
                        'dimensions' => 'ga:pagePath',
                        'sort' => '-ga:'.$metric,
                        'max-results' => 50,
                        'filters' => $hostStr.'ga:pagePath'.$op.$path
                    ));
                    break;
                } catch (\Exception $e) {
                    if (!--$tries) {
                        throw new Exception('Api-Error:' .$e->getMessage(), $e->getCode(), $e);
                    }
                    usleep(rand(300000,500000)+pow(2,2-$tries)*1000000);
                }
            };


            $count = $data['totalsForAllResults']['ga:'.$metric];

            if ($logger = $worker->getLogger()) {
                $logger->logDebug('Analytics getMetric ('.$metric.'): '.(!empty($params['hostname']) ? 'Host='.$params['hostname'] . ' | ' : '').'Path='.$path.' | COUNT='.$count);
            }
        }
        
        return (int) $count;
    }
    
    public function getPageviews($params, $config, $job, $worker)
    {
        $params['metric'] = 'pageviews';
        return $this->getMetric($params, $config, $job, $worker);
    }
    
    public function getVisits($params, $config, $job, $worker)
    {
        $params['metric'] = 'visits';
        return $this->getMetric($params, $config, $job, $worker);
    }
    
    public function getEventData($params, $config, $job, $worker)
    {
        if (empty($config['clientKey']) || empty($config['clientSecret'])) {
            throw new \Exception('Config parameters clientKey and clientSecret are required!');
        }
        
        if (empty($params['eventCategory']) || empty($params['eventAction']) || empty($params['profileId']) || empty($params['refreshToken'])) {
            throw new \Exception('parameters eventCategory, eventAction, profileId and refreshToken are required!');
        }
        
        $service = $this->initClient($config['clientKey'], $config['clientSecret'], $params['refreshToken'], $worker->getConnection(), $worker->getLogger());
        
        $dateFrom = !empty($params['dateFrom']) ? $params['dateFrom'] : '2005-01-01';
        $dateTo =  !empty($params['dateTo']) ? $params['dateTo'] : date('Y-m-d');
        
        $tries = 3;
        while(true) {
            try {    
                $data = $service->data_ga->get('ga:'.$params['profileId'], $dateFrom, $dateTo, 'ga:totalEvents,ga:eventValue', array(
                    'dimensions' => 'ga:eventCategory,ga:eventAction',
                    'max-results' => 50,
                    'filters' => 'ga:eventCategory=='.$params['eventCategory'].';ga:eventAction=='.$params['eventAction']
                ));
                break;
            } catch (\Exception $e) {
                if (!--$tries) {
                    throw new Exception('Api-Error:' .$e->getMessage(), $e->getCode(), $e);
                }
                usleep(50000);
            }
        };
        
        
        $numEvents = $data['totalsForAllResults']['ga:totalEvents'];
        $scoreEvents = $data['totalsForAllResults']['ga:eventValue'];
        
        if ($logger = $worker->getLogger()) {
            $logger->logDebug('Analytics EventData: '.$params['eventCategory'].':'.$params['eventAction'].' | COUNT='.$numEvents.' | SCORE='.$scoreEvents);
        }

        
        return array(
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'eventCategory' => $params['eventCategory'],
            'eventAction' => $params['eventAction'],
            'count' => $numEvents,
            'score' => $scoreEvents
        );
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
