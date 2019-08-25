<?php
require("./vendor/autoload.php");

use Doctrine\Common\ClassLoader,
    Doctrine\ORM\Configuration,
    Doctrine\ORM\EntityManager,
    Doctrine\Common\Cache\ArrayCache,
    Doctrine\DBAL\Logging\EchoSQLLogger;

class Doctrine{

  public $em = null;

  public function __construct()
  {

    require_once 'Doctrine/Common/ClassLoader.php';

    $doctrineClassLoader = new ClassLoader('Doctrine',  '/');
    $doctrineClassLoader->register();
    $entitiesClassLoader = new ClassLoader('models', '/models/');
    $entitiesClassLoader->register();
    $proxiesClassLoader = new ClassLoader('Proxies', '/proxies/');
    $proxiesClassLoader->register();

    // Set up caches
    $config = new Configuration;
    $cache = new ArrayCache;
    $config->setMetadataCacheImpl($cache);
    $driverImpl = $config->newDefaultAnnotationDriver(array('/models/Entities'));
    $config->setMetadataDriverImpl($driverImpl);
    $config->setQueryCacheImpl($cache);

    $config->setQueryCacheImpl($cache);

    // Proxy configuration
    $config->setProxyDir('/proxies');
    $config->setProxyNamespace('Proxies');

    // Set up logger
    $logger = new EchoSQLLogger;
    //$config->setSQLLogger($logger);

    $config->setAutoGenerateProxyClasses( TRUE );

    // Database connection information
    $connectionOptions = array(
        'driver' => 'pdo_mysql',
        'user' =>     'root',
        'password' => '',
        'host' =>     'localhost',
        'dbname' =>   'cscms'
    );

    // Create EntityManager
    $this->em = EntityManager::create($connectionOptions, $config);
  }
}