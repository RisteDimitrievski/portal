<?php 
class Autoloader
{
	public static function load($classname){
		require('./' . __NAMESPACE__ . $classname . '.class.php');
	}
}
spl_autoload_register(__NAMESPACE__ . "\\Autoloader::load");
?>