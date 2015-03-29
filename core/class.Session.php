<?php
/*
	Session
	Manage Sessions
	Dependencies : Loader
*/

class Session
{
	public static $life_time = 604800; // 1 week

	public function __construct()
	{
		session_set_cookie_params(self::$life_time);
		ini_set('session.gc_maxlifetime', self::$life_time);

		if (!isset($_SESSION))
			session_start();

		if (!session_id())
			session_regenerate_id();
	}

	public function check($name)
	{
		if (!isset($_SESSION[$name]))
			return false;

		$hash = $this->token();

		if ($_SESSION[$name] < time())
		{
			unset($_SESSION[$name]);
			session_destroy();
			return false;
		}

		return true;
	}

	public function secure($name)
	{
		$_SESSION[$name] = time() + self::$life_time;
	}

	public function kill($name)
	{
		unset($_SESSION[$name]);
		session_destroy();
	}
}
