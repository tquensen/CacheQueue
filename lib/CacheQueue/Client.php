<?php
namespace CacheQueue;

class Client implements IClient
{
    private $connection;
    
    public function __construct(IConnection $connection)
    {
        $this->connection = $connection;
    }

    public function get($key)
    {
        $result = $this->connection->get($key);
        if (!$result || empty($result['data'])) {
            return false;
        }
        return $result['data'];
    }

    public function getOrQueue($key, $task, $params, $freshUntil)
    {
        $result = $this->connection->get($key);
        if (!$result || $result['fresh_until'] < time()) {
            $this->queue($key, $task, $params, $freshUntil);
        }
        return empty($result['data']) ? false : $result['data'];
    }

    public function queue($key, $task, $params, $freshUntil)
    {
        return $this->connection->queue($key, $task, $params, $freshUntil);
    }

}
