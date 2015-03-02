<?php
/*
	Session
	Manage Sessions
	Dependencies : Loader
*/

class Session
{
	public static $life_time = 604800; // 1 week
	public static $unique = 0;

	public function __construct()
	{
		session_set_cookie_params(self::$life_time);
		ini_set('session.gc_maxlifetime', self::$life_time);

		if (!isset($_SESSION))
			session_start();

		if (!session_id())
			session_regenerate_id();
	}

	public function check($name, $key=0)
	{
		if (!isset($_SESSION[$name]))
			return false;

		if (!$key)
			return true; 
		
		$hash = $this->token($key);

		if (($_SESSION[$name]['token'] != $hash) || ($_SESSION[$name]['time'] < time()))
		{
			unset($_SESSION[$name]);
			session_destroy();
			return false;
		}

		return true;
	}

	public function secure($name, $key)
	{
		$_SESSION[$name] = array();
		$_SESSION[$name]['token'] = $this->token($key);
		$_SESSION[$name]['time'] = time() + self::$life_time;
	}

	public function token($key)
	{
		return hash('sha256', $key.((self::$unique)? $_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR']:''));
	}

	public function kill($name)
	{
		unset($_SESSION[$name]);
		session_destroy();
	}
}
