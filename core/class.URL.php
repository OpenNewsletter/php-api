<?php
/**
	URL
	Description : Manage URLs
	Dependencies : Loader
*/

class URL
{
	private static $headers = null;
	public static $prefix = '';
	public static $suffix = '';
	public static $nemesisUrl = NEMESIS_URL;
	public static $request = array(
		'HASH' => array(),
		'QUERY_STRING' => '',
		'DOMAIN' => '',
		'SUBDOMAIN'  => '',
		'SOURCE' => ''
	);

	public function __construct($str, $prefix = '', $suffix = '')
	{
		if ($prefix)
			self::$prefix = $prefix;

		if ($suffix)
			self::$suffix = $suffix;

		$this->url = beautify($str);
	}

	public function __toString()
	{
		return (self::$nemesisUrl).(self::$prefix).((empty($this->url) || $this->url == '/')? '':($this->url.((self::$suffix)? '/'.(self::$suffix):'')));
	}

	public static function splitRequest($request)
	{
		$request = trim($request, '/');

		self::$request['SOURCE'] = $request;
		self::$request['HASH'] = explode('/', $request);

		$domain = explode('.', NEMESIS_HOST);

		if (!empty($domain) && count($domain) > 2)
		{
			self::$request['SUBDOMAIN'] = $domain[0];
			self::$request['DOMAIN'] = $domain[1].'.'.$domain[2];
		}
		else
			self::$request['DOMAIN'] = NEMESIS_HOST;
	}

	public static function getHash($i)
	{
		if ($i >=0 && isset(self::$request['HASH'][$i]))
			return self::$request['HASH'][$i];
	}


	public static function redirect($url, $target=0)
	{
		if(!headers_sent())
		{
			header("Location: ".(($target)? rtrim(NEMESIS_URL, '/'):'').str_replace('//', '/', '/'.trim($url, '/').'/'));
			exit();
		}
		else
		{
			echo "Headers already sent\n";
			exit();
		}
	}

	public static function getHeaders()
	{
		if (empty(self::$headers))
		{
			foreach ($_SERVER as $k => $v)
			{
				if (substr($k, 0, 5) == "HTTP_")
				{
					$k = str_replace('_', ' ', substr($k, 5));
					$k = str_replace(' ', '-', ucwords(strtolower($k)));
					self::$headers[$k] = $v;
				}
			}
		}
		return self::$headers;
	}

	public static function isHttpRequest()
	{
		self::getHeaders();
		return isset(self::$headers['X-Requested-With']) || isset(self::$headers['X-Request']);
	}

}
