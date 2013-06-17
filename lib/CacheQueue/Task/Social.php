<?php

namespace CacheQueue\Task;

use CacheQueue\Exception\Exception;

class Social
{

    public function getRetweets($params, $config, $job, $worker)
    {
        // set timeout
        $context = stream_context_create(array('http' => array('timeout' => 15)));

        // get count data from twitter
        $rawData = @file_get_contents('http://urls.api.twitter.com/1/urls/count.json?url=' . $params, 0, $context);

        if ($twitterData = @json_decode($rawData)) {
            return (int) $twitterData->count;
        }
    }

    public function getLikes($params, $config, $job, $worker)
    {
        // set timeout
        $context = stream_context_create(array('http' => array('timeout' => 15)));

        //FQL workaround for bug https://developers.facebook.com/bugs/180781098727185?browse=search_50dda74e1870c8d60275326
        $rawData = @file_get_contents('https://api.facebook.com/method/fql.query?format=json&query=select%20total_count%20from%20link_stat%20where%20url%20=%20%22' . $params . '%22', 0, $context);

        if (($facebookData = @json_decode($rawData)) && isset($facebookData[0]->total_count)) {
            return (int) $facebookData[0]->total_count;
        }
        return null;
        //end of workaround
        // get count data from facebook
        // see: https://developers.facebook.com/docs/reference/api/
        $rawData = @file_get_contents('http://graph.facebook.com/?ids=' . $params, 0, $context);

        if (($facebookData = @json_decode($rawData)) && isset($facebookData->$params->shares)) {
            return (int) $facebookData->$params->shares;
        }
    }

    public function getPlusOnes($params, $config, $job, $worker)
    {
        // set context
        /* no longer works as of mai 2013 
          $context = stream_context_create(array(
          'http' => array(
          'timeout' => 15,
          'method' => 'POST',
          'header' => 'Content-type: application/json'."\r\n",
          'content' => '[{"method":"pos.plusones.get","id":"p","params":{"nolog":true,"id":"' . $params . '","source":"widget","userId":"@viewer","groupId":"@self"},"jsonrpc":"2.0","key":"p","apiVersion":"v1"}]'
          )
          ));

          $rawData = @file_get_contents('https://clients6.google.com/rpc?key=AIzaSyCKSbrvQasunBoV16zDH9R33D88CeLr9gQ', 0, $context);

          if(($plusOneData = @json_decode($rawData)) && isset($plusOneData[0]->result->metadata->globalCounts->count)) {
          return (int) $plusOneData[0]->result->metadata->globalCounts->count;
          }
         */

        //alternative method from http://shinraholdings.com/870/count-facebook-likes-twitter-links-and-google-pluses-with-php/#google-plus

        $context = stream_context_create(array('http' => array('timeout' => 15)));
        $contents = @file_get_contents('https://plusone.google.com/_/+1/fastbutton?url=' . $params, 1, $context);

        /* pull out count variable with regex */
        preg_match('/window\.__SSR = {c: ([\d]+)/', $contents, $matches);

        /* if matched, return count, else zed */
        if (!empty($matches[1])) {
            return (int) $matches[1];
        }
        return 0;
    }

    public function getTwitterTimeline($params, $options, $job, $worker)
    {
        if (empty($params['screen_name']) && empty($params['user_id'])) {
            throw new \Exception('getTwitterTimeline: either screen_name or user_id parameter is required!');
        }

        if (empty($config['consumerKey']) || empty($config['consumerSecret'])) {
            throw new \Exception('getTwitterTimeline: Config parameters consumerKey and consumerSecret are required!');
        }

        $twitterToken = $this->getTwitterToken($config['consumerKey'], $config['consumerSecret'], $worker->getConnection(), $worker->getLogger());

        // set timeout
        $context = stream_context_create(array('http' => array(
                'timeout' => 15,
                'header' => 'Authorization: Bearer ' . $twitterToken . "\r\n",
        )));

        if (empty($params['limit'])) {
            $limit = 30;
        } else {
            $limit = (int) $params['limit'];
        }

        $update = empty($params['update']) ? false : true;
        $filter = empty($params['filter']) ? false : $params['filter'];

        $url = 'https://api.twitter.com/1.1/statuses/user_timeline.json?count=' . $limit;

        if (!empty($params['screen_name'])) {
            $url .= '&screen_name=' . $params['screen_name'];
        } else {
            $url .= '&user_id=' . $params['user_id'];
        }

        $rawData = @file_get_contents($url);
        $rawTweets = @json_decode($rawData, true);
        $tweets = array();

        if (!$rawTweets) {
            throw new Exception('getTwitterTimeline: error recieving tweets from url ' . $url);
        }

        foreach ($rawTweets AS $rawTweet) {
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
            $logger->logDebug('getTwitterTimeline: ' . $countNew . ' new tweets / ' . $countTotal . ' total.');
        }

        return $tweets;
    }

    private function getTwitterToken($consumerKey, $consumerSecret, $connection, $logger)
    {
        $twitterTokenCacheKey = 'twitter_token_' . md5($consumerKey . $consumerSecret);

        $cachedTokenData = $connection->get($twitterTokenCacheKey);
        if (!$cachedTokenData || !$cachedTokenData['is_fresh']) {
            $lockKey = $connection->obtainLock($twitterTokenCacheKey, 5);
            if ($lockKey) {
                $cachedTokenData = $connection->get($twitterTokenCacheKey);
                if (!$cachedTokenData || !$cachedTokenData['is_fresh']) {
                    try {
                        if ($logger) {
                            $logger->logDebug('Twitter: refreshing bearer token');
                        }

                        $encodedAuth = base64_encode(urlencode($consumerKey) . ':' . urlencode($consumerSecret));

                        $context = stream_context_create(array(
                            'http' => array(
                                'timeout' => 15,
                                'method' => 'POST',
                                'header' => 'Content-type: application/x-www-form-urlencoded;charset=UTF-8' . "\r\n"
                                . 'Authorization: Basic ' . $encodedAuth . "\r\n",
                                'content' => 'grant_type=client_credentials'
                            )
                        ));

                        $rawData = @file_get_contents('https://api.twitter.com/oauth2/token', 0, $context);
                        $tmp = @json_decode($rawData, true);

                        if (!$tmp || empty($tmp['access_token'])) {
                            throw new Exception('Could not get Twitter bearer token: invalid response');
                        }
                        $token = $tmp['access_token'];
                        $connection->set($twitterTokenCacheKey, $token, 3600, true);
                        $connection->releaseLock($twitterTokenCacheKey, $lockKey);
                        return $token;
                    } catch (\Exception $e) {
                        $connection->releaseLock($twitterTokenCacheKey, $lockKey);
                        throw $e;
                    }
                }
                $connection->releaseLock($twitterTokenCacheKey, $lockKey);
            } else {
                throw new Exception('Could not get Twitter bearer token');
            }
        }

        return $cachedTokenData['data'];
    }

}
