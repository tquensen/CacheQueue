<?php
namespace CacheQueue\Task;

class Social
{
    public function getRetweets($params, $config, $job)
    {
        // set timeout
		$context = stream_context_create(array('http' => array('timeout' => 15)));
		
		// get count data from twitter
		$rawData = file_get_contents('http://urls.api.twitter.com/1/urls/count.json?url=' . $params, 0, $context);
		
		if($twitterData = json_decode($rawData)) {
			return (int) $twitterData->count;
		}
    }
    
    public function getLikes($params, $config, $job)
    {
        // set timeout
		$context = stream_context_create(array('http' => array('timeout' => 15)));
		
		// get count data from facebook
		// see: https://developers.facebook.com/docs/reference/api/
		$rawData = file_get_contents('http://graph.facebook.com/?ids=' . $params, 0, $context);
		
		if(($facebookData = json_decode($rawData)) && isset($facebookData->$params->shares)) {
			return (int) $facebookData->$params->shares;
		}
    }
    
    public function getPlusOnes($params, $config, $job)
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
        
        $rawData = file_get_contents('https://clients6.google.com/rpc', 0, $context);
        
        if(($plusOneData = json_decode($rawData)) && isset($plusOneData[0]->result->metadata->globalCounts->count)) {
			return (int) $plusOneData[0]->result->metadata->globalCounts->count;
		}        
    }
}
