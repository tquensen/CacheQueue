<?php
namespace CacheQueue\Task;

class Social
{
    public function getRetweets($params, $config, $job, $worker)
    {
        // set timeout
		$context = stream_context_create(array('http' => array('timeout' => 15)));
		
		// get count data from twitter
		$rawData = @file_get_contents('http://urls.api.twitter.com/1/urls/count.json?url=' . $params, 0, $context);
		
		if($twitterData = @json_decode($rawData)) {
			return (int) $twitterData->count;
		}
    }
    
    public function getLikes($params, $config, $job, $worker)
    {
        // set timeout
		$context = stream_context_create(array('http' => array('timeout' => 15)));
		
		// get count data from facebook
		// see: https://developers.facebook.com/docs/reference/api/
		$rawData = @file_get_contents('http://graph.facebook.com/?ids=' . $params, 0, $context);
		
		if(($facebookData = @json_decode($rawData)) && isset($facebookData->$params->shares)) {
			return (int) $facebookData->$params->shares;
		}
    }
    
    public function getPlusOnes($params, $config, $job, $worker)
    {
        // set context
		$context = stream_context_create(array(
            'http' => array(
                'timeout' => 15,
                'method' => 'POST',
                'header' => 'Content-type: application/json'."\r\n",
                'content' => '[{"method":"pos.plusones.get","id":"p","params":{"nolog":true,"id":"' . $params . '","source":"widget","userId":"@viewer","groupId":"@self"},"jsonrpc":"2.0","key":"p","apiVersion":"v1"}]'
            )
        ));
        
        $rawData = @file_get_contents('https://clients6.google.com/rpc', 0, $context);
        
        if(($plusOneData = @json_decode($rawData)) && isset($plusOneData[0]->result->metadata->globalCounts->count)) {
			return (int) $plusOneData[0]->result->metadata->globalCounts->count;
		}        
    }
    
    public function getTwitterTimeline($params, $options, $job, $worker)
    {
        if (empty($params['account'])) {
            throw new \Exception('getLinkNews: parameters account is required!');
        }
        
        if (empty($params['limit'])) {
            $limit = 30;
        } else {
            $limit = (int) $params['limit'];
        }
        
        $update = empty($params['update']) ? false : true;
        $filter = empty($params['filter']) ? false : $params['filter'];
        
        $url = 'http://twitter.com/statuses/user_timeline/'.trim($params['account']).'.json?count=' . $limit;
        
        $rawData = @file_get_contents($url);
        $rawTweets    = @json_decode($rawData, true);
        $tweets = array();
        
        if (!$rawTweets) {
            throw new Exception('getTwitterTimeline: error recieving tweets from url '.$url);
        }
        
        foreach($rawTweets AS $rawTweet) {
            if (!$filter || preg_match($filter, $rawTweet['text'])) {
                $tweets[$rawTweet['id']] = $rawTweet;
            }
        }
        
        $countNew = count($tweets);
        if ($update) {
            $oldTweets = $job['data'];
            if ($oldTweets) {
                $count = $countNew;
                foreach ($oldTweets as $tweetId => $tweet) {
                    if ($count >= $limit) {
                        break;
                    }
                    $tweets[$tweetId] = $tweet;
                    $count++;
                }
            }
        }
        
        $countTotal = count($tweets);
        
        if ($logger = $worker->getLogger()) {
            $logger->logDebug('getTwitterTimeline: '.$countNew.' new tweets / '.$countTotal.' total.');
        }
        
        return $tweets;
    }
}
