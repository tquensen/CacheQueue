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
            'requestScheme' => Zend_Oauth::REQUEST_SCHEME_HEADER,
            'version' => '1.0',
            'signatureMethod' => 'HMAC-SHA1', 
            //'callbackUrl' => 'http://example.com/',
            //'siteUrl' => 'http://example.com/',
            'consumerKey' => $consumerKey,
            'consumerSecret' => $consumerSecret
        );
        $token = new Zend_Oauth_Token_Access();
        $token->setToken($token);
        $token->setTokenSecret($tokenSecret);
        $client = $token->getHttpClient($config);
        $this->client = new Zend_Gdata($client);
        return $this->client;
    }
    
    public function getPageviews($params, $config, $job)
    {
        if (empty($config['consumerKey']) || empty($config['consumerSecret'])) {
            throw new \Exception('Config parameters consumerKey and consumerSecret are required!');
        }
        
        if (empty($params['url']) || empty($params['profileId']) || empty($params['token']) || empty($params['tokenSecret'])) {
            throw new \Exception('parameters parameters url, profileId, token and tokenSecret are required!');
        }
        
        $url = str_replace(array('https://', 'http://'), '', $params['url']);
        $tmp = explode('/', $url, 2);
        $path = isset($tmp[1]) ? $tmp[1] : '';
        
        $client = $this->initClient($config['consumerKey'], $config['consumerSecret'], $params['token'], $params['tokenSecret']);
        
        $dateFrom = '2005-01-01';
        $dateTo = date('Y-m-d');
        
        $reportURL = 'https://www.google.com/analytics/feeds/data?ids=ga:'.$params['profileId'].'&dimensions=ga:pagePath&metrics=ga:pageviews&start-date='.$dateFrom.'&end-date='.$dateTo.'&sort=-ga:pageviews&max-results=1&filters=ga:pagePath%3D%3D'.urlencode($url);

        $count = 0;

        $results = self::$client->getFeed($reportURL);
        $xml = $results->getXML();

        Zend_Feed::lookupNamespace('default');
        $feed = new Zend_Feed_Atom(null, $xml);
        foreach($feed as $entry) {
            $count = (int)$entry->metric->getDOM()->getAttribute('value');
            break;
        }

        return (int) $count;
    }
    
    
}
