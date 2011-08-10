<?php
namespace CacheQueue\Task;

class Symfony
{
    public function runTask($params, $config, $job, $worker)
    {
        if (empty($config['symfonyBaseDir'])) {
            throw new \Exception('Config parameter symfonyBaseDir is required!');
        }
        $dir = rtrim($config['symfonyBaseDir'], '/\\');
        chdir($dir);
        
        require_once($dir.'/config/ProjectConfiguration.class.php');
        include(\sfCoreAutoload::getInstance()->getBaseDir().'/command/cli.php');

        try
        {
          $dispatcher = new \sfEventDispatcher();
          $logger = new \sfCommandLogger($dispatcher);

          $application = new \sfSymfonyCommandApplication($dispatcher, null, array('symfony_lib_dir' => \sfCoreAutoload::getInstance()->getBaseDir()));
          $statusCode = $application->run($params);
        }
        catch (\Exception $e)
        {
          if (!isset($application))
          {
            throw $e;
          }

          $application->renderException($e);
          $statusCode = $e->getCode();

          return is_numeric($statusCode) && $statusCode ? $statusCode : 1;
        }
        
        return is_numeric($statusCode) && $statusCode ? $statusCode : 1;
    }
}
