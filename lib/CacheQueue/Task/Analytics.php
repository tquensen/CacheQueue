<?php
namespace CacheQueue\Task;

class Analytics
{
    private $client = null;
    
    private function initClient($consumerKey, $consumerSecret, $token, $tokenSecret)
    {
        require_once 'Zend/Feed/Atom.php';
        require_once 'Zend/Gdata.php';
        require_once 'Zend/Oauth/Token/Access.php';
        $config = array(
            'requestScheme' => \Zend_Oauth::REQUEST_SCHEME_HEADER,
            'version' => '1.0',
            'signatureMethod' => 'HMAC-SHA1', 
            //'callbackUrl' => 'http://example.com/',
            //'siteUrl' => 'http://example.com/',
            'consumerKey' => $consumerKey,
            'consumerSecret' => $consumerSecret
        );
        $oAuthToken = new \Zend_Oauth_Token_Access();
        $oAuthToken->setToken($token);
        $oAuthToken->setTokenSecret($tokenSecret);
        $client = $oAuthToken->getHttpClient($config);
        $this->client = new \Zend_Gdata($client);
        return $this->client;
    }
    
    public function getPageviews($params, $config, $job, $worker)
    {
        if (empty($config['consumerKey']) || empty($config['consumerSecret'])) {
            throw new \Exception('Config parameters consumerKey and consumerSecret are required!');
        }
        
        if (empty($params['pagePath']) || empty($params['profileId']) || empty($params['token']) || empty($params['tokenSecret'])) {
            throw new \Exception('parameters pagePath, profileId, token and tokenSecret are required!');
        }
        
//        $url = str_replace(array('https://', 'http://'), '', $params['pagePath']);
//        $tmp = explode('/', $url, 2);
//        $path = '/'. (isset($tmp[1]) ? $tmp[1] : '');
        
        
        $path = $params['pagePath'];
        $hostStr = !empty($params['hostname']) ? 'ga:hostname%3D%3D'.$params['hostname'].';' : '';
        
        $op = !empty($params['operator']) ? $params['operator'] : '==';
        
        $client = $this->initClient($config['consumerKey'], $config['consumerSecret'], $params['token'], $params['tokenSecret']);
        
        $dateFrom = '2005-01-01';
        $dateTo = date('Y-m-d');
        
        $reportURL = 'https://www.google.com/analytics/feeds/data?ids=ga:'.$params['profileId'].'&dimensions=ga:pagePath&metrics=ga:pageviews&start-date='.$dateFrom.'&end-date='.$dateTo.'&sort=-ga:pageviews&max-results=50&filters='.$hostStr.'ga:pagePath'.urlencode($op.$path);

        $count = 0;

        $results = $client->getFeed($reportURL);
        $xml = $results->getXML();

        \Zend_Feed::lookupNamespace('default');
        $feed = new \Zend_Feed_Atom(null, $xml);
        foreach($feed as $entry) {
            $count += (int)$entry->metric->getDOM()->getAttribute('value');
        }
        
        if ($logger = $worker->getLogger()) {
            $logger->logDebug('Analytics: URL='.$reportURL.' / COUNT='.$count);
        }

        return (int) $count;
    }
    
    public function getTopUrls($params, $config, $job, $worker)
    {
        if (empty($config['consumerKey']) || empty($config['consumerSecret'])) {
            throw new \Exception('Config parameters consumerKey and consumerSecret are required!');
        }
        
        if (empty($params['profileId']) || empty($params['token']) || empty($params['tokenSecret'])) {
            throw new \Exception('parameters, profileId, token and tokenSecret are required!');
        }
        
        if (empty($params['pathPrefix'])) {
            $params['pathPrefix'] = '/';
        }
        
        if (!empty($params['count'])) {
            $limit = $params['count'];
        } else {
            $limit = !empty($config['count']) ? $config['count'] : 10;
        }
         
        $client = $this->initClient($config['consumerKey'], $config['consumerSecret'], $params['token'], $params['tokenSecret']);
        
        $dateFrom = !empty($params['dateFrom']) ? $params['dateFrom'] : '2005-01-01';
        $dateTo = !empty($params['dateTo']) ? $params['dateTo'] : date('Y-m-d');
        
        $reportURL = 'https://www.google.com/analytics/feeds/data?ids=ga:'.$params['profileId'].'&dimensions=ga:pagePath&metrics=ga:pageviews&start-date='.$dateFrom.'&end-date='.$dateTo.'&sort=-ga:pageviews&max-results='.$limit.'&filters=ga:pagePath%3D~%5E'.urlencode($params['pathPrefix']);

        $results = $client->getFeed($reportURL);
        $xml = $results->getXML();

        \Zend_Feed::lookupNamespace('default');
        $feed = new \Zend_Feed_Atom(null, $xml);
        $topUrlsTmp = array();

        foreach($feed as $entry) {
            $title = (string)$entry->dimension->getDOM()->getAttribute('value');
            $count = (int)$entry->metric->getDOM()->getAttribute('value');

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
            $logger->logDebug('Analytics: TopUrls / COUNT='.count($topUrls));
        }

        return $topUrls;
    }
    
    public function getTopKeywords($params, $config, $job, $worker)
    {
        if (empty($config['consumerKey']) || empty($config['consumerSecret'])) {
            throw new \Exception('Config parameters consumerKey and consumerSecret are required!');
        }
        
        if (empty($params['profileId']) || empty($params['token']) || empty($params['tokenSecret'])) {
            throw new \Exception('parameters, profileId, token and tokenSecret are required!');
        }
        
        if (empty($params['pathPrefix'])) {
            $params['pathPrefix'] = '/';
        }
        
        if (!empty($params['count'])) {
            $limit = $params['count'];
        } else {
            $limit = !empty($config['count']) ? $config['count'] : 10;
        }
         
        $client = $this->initClient($config['consumerKey'], $config['consumerSecret'], $params['token'], $params['tokenSecret']);
        
        $dateFrom = !empty($params['dateFrom']) ? $params['dateFrom'] : date('Y-m-d',  mktime(0, 0, 0, date('m')-1, date('d'), date('Y')));
        $dateTo = !empty($params['dateTo']) ? $params['dateTo'] : date('Y-m-d');
        
        $reportURL = 'https://www.google.com/analytics/feeds/data?ids=ga:'.$params['profileId'].'&dimensions=ga:keyword&metrics=ga:pageviews&start-date='.$dateFrom.'&end-date='.$dateTo.'&sort=-ga:pageviews&max-results='.$limit.'&filters=ga:pagePath%3D~%5E'.urlencode($params['pathPrefix']);

        $results = $client->getFeed($reportURL);
        $xml = $results->getXML();

        \Zend_Feed::lookupNamespace('default');
        $feed = new \Zend_Feed_Atom(null, $xml);
        $topKeywordsTmp = array();

        foreach($feed as $entry) {
            $title = (string)$entry->dimension->getDOM()->getAttribute('value');
            $count = (int)$entry->metric->getDOM()->getAttribute('value');

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
            $logger->logDebug('Analytics: TopKeywords / COUNT='.count($topKeywords));
        }

        return $topKeywords;
    }
}
