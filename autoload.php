<?php 
class ClassLoader
{
	public static function load($class){
		require './' . __NAMESPACE__ . $class . '.php';
	}
}
spl_autoload_register(__NAMESPACE__ . "\\ClassLoader::load");