<?php
/*
	Router
	Manage Routes
	Dependencies : Loader, URL
*/

class Router
{	
	public function __construct() 
	{
		// Split request from current URL
		$exploded = explode('?', $_SERVER['REQUEST_URI']);
		if (sizeof($exploded) > 0)
			$request = $exploded[0];
		else
			$request = $_SERVER['REQUEST_URI'];

		if (NEMESIS_ROOT && NEMESIS_ROOT != '/')
			$request = str_replace (NEMESIS_ROOT, '', $request);

		// if (!empty($request) && (substr($request, -1) != '/') && !extension($request))
			// URL::redirect($request, 1);

		URL::splitRequest($request);
		URL::$request['QUERY_STRING'] = $_SERVER['QUERY_STRING'];
	}
}
