<?php
/*
	Class Loader
	Description : Autoload and initialization classes
*/

class Loader
{

	private static $instance = null;
	
	public static function getInstance ()
    {
		if (is_null(self::$instance))
		{
			self::$instance = new Loader ();
		}

		return self::$instance;
	}

	public function __construct()
	{
		// Autoload class core
		spl_autoload_register(
			function ($className)
			{
				
				if (file_exists($classFile=CORE.'class.'. $className . '.php'))
				{
					require_once($classFile);
				}
			}
		);
	}
	
	public function initClass($extendedClass=array())
	{
		if (!is_array($extendedClass))
		{
			$instanceClass = new $extendedClass();
			unset ($instanceClass);
		}
		else 
		{
			foreach ($extendedClass as $class) 
			{
				$classI = new $class();
				unset ($classI);
			}
		}
	}
}
