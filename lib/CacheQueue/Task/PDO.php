<?php
namespace CacheQueue\Task;
use CacheQueue\Exception\Exception;

/**
 * queue and perform SQL queries using PDO
 * 
 * all tasks require a valid DNS, username and password. 
 *  
 */
class PDO
{
    /**
     *
     * @var \PDO
     */
    protected $pdo = null;
    
    protected function connect($config, $params)
    {
        if (empty($config['dns']) && empty($params['dns'])) {
            throw new \Exception('config parameter dns is required!');
        }
        if (empty($config['user']) && empty($params['user'])) {
            throw new \Exception('config parameter user is required!');
        }
        if (empty($config['pass']) && empty($params['pass'])) {
            throw new \Exception('config parameter pass is required!');
        }
        try {
            $this->pdo = new \PDO(!empty($params['dns']) ? $params['dns'] : $config['dns'], !empty($params['user']) ? $params['user'] : $config['user'], !empty($params['pass']) ? $params['pass'] : $config['pass'], !empty($params['options']) ? $params['options'] : (!empty($config['options']) ? $config['options'] : array()));
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            if ($this->pdo && $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) == 'mysql') {
                $this->pdo->exec('SET CHARACTER SET utf8');
            }
        } catch (\PDOException $e) {
            throw new \Exception('PDO Exception: '.$e);
        }
    }
    
    public function execute($params, $config, $job, $worker)
    {
        if (empty($params['query'])) {
            throw new \Exception('parameter query is required!');
        }
        $this->connect($config, $params);
        $stmt = $this->pdo->prepare($params['query']);
        try {
            $result = $stmt->execute(!empty($params['parameter']) ? $params['parameter'] : null);
            if (!empty($params['return'])) {
                switch ($params['return']) {
                    case 'rowCount':
                        return $stmt->rowCount();
                        break;
                    case 'row':
                        return $stmt->fetch(!empty($params['fetchStyle']) ? $params['fetchStyle'] : \PDO::FETCH_ASSOC);
                        break;
                    case 'column':
                        return $stmt->fetchColumn(!empty($params['column']) ? $params['column'] : 0);
                        break;
                    case 'all':
                        return $stmt->fetchAll(!empty($params['fetchStyle']) ? $params['fetchStyle'] : \PDO::FETCH_ASSOC, !empty($params['fetchArgument']) ? $params['fetchArgument'] : null);
                        break;      
                }
            }
            return $result;
        } catch (\PDOException $e) {
            throw new Exception('PDO Query failed: '.$e->getMessage());
        }  
    }
    
    public function executeMultiple($params, $config, $job, $worker)
    {
        if (empty($params['query']) || !is_array($params['query'])) {
            throw new \Exception('parameter query is required and must be an array!');
        }
        $this->connect($config, $params);
        $allResults = array();
        foreach ($params['query'] as $key => $query) {
            $stmt = $this->pdo->prepare($query);
            try {
                $result = $stmt->execute(!empty($params['parameter'][$key]) ? $params['parameter'][$key] : null);
                $returnType = !empty($params['return'][$key]) ? $params['return'][$key] : false;
                    
                    switch ($params['return'][$key]) {
                        case 'rowCount':
                            $allResults[$key] = $stmt->rowCount();
                            break;
                        case 'row':
                            $allResults[$key] = $stmt->fetch(!empty($params['fetchStyle'][$key]) ? $params['fetchStyle'][$key] : \PDO::FETCH_ASSOC);
                            break;
                        case 'column':
                            $allResults[$key] = $stmt->fetchColumn(!empty($params['column'][$key]) ? $params['column'][$key] : 0);
                            break;
                        case 'all':
                            $allResults[$key] = $stmt->fetchAll(!empty($params['fetchStyle'][$key]) ? $params['fetchStyle'][$key] : \PDO::FETCH_ASSOC, !empty($params['fetchArgument'][$key]) ? $params['fetchArgument'][$key] : null);
                            break; 
                        default:
                            $allResults[$key] = $result;
                    }
            } catch (\PDOException $e) {
                throw new Exception('PDO Query failed: '.$e->getMessage());
            }  
        }
        
        return $allResults;
    }

}
