<?php 
require_once("vendor/autoload.php");
include_once("./constants.php");
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;
$devMode = false;
$databaseInfo = array(
 'driver' => 'pdo_mysql',
 'user' => 'root',
 'password' => '',
 'dbname' => 'csportal',
);
$config = Setup::createAnnotationMetadataConfiguration(ENTITY_PATH,$devMode);
$entityManager = EntityManager::create($databaseInfo,$config);
