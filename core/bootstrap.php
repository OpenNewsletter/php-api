<?php
/*
	Bootstrap
*/

// PATHS
define('NEMESIS_PATH', str_replace('\\', '/', str_replace('//', '/', str_replace('core', '', __DIR__))));
define('CORE', NEMESIS_PATH.'core/');

// BASE URL
define('NEMESIS_ROOT', str_replace('//', '/', dirname($_SERVER['SCRIPT_NAME']) . '/'));
define('NEMESIS_SCHEME', ( isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://');
define('NEMESIS_PORT', (isset($_SERVER['SERVER_PORT']) && (($_SERVER['SERVER_PORT'] != '80' && NEMESIS_SCHEME == 'http://') || ($_SERVER['SERVER_PORT'] != '443' && NEMESIS_SCHEME == 'https://')) && strpos($_SERVER['HTTP_HOST'], ':') === false) ? ':'.$_SERVER['SERVER_PORT'] : '');
define('NEMESIS_HOST', preg_replace('/:'.NEMESIS_PORT.'$/', '', $_SERVER['HTTP_HOST']));
define('NEMESIS_URL', str_replace('\\', '', (trim( urldecode( NEMESIS_SCHEME . NEMESIS_HOST )). str_replace('//', '/', NEMESIS_ROOT.'/'))));

// ERRORS
error_reporting(E_ALL);
ini_set('display_errors', 'On');
ini_set('log_errors', 'On');
ini_set('error_log', CORE.'errors.log');
ini_set('ignore_repeated_errors', 'On');

// Include core functions
function core_functions() 
{
	require_once CORE . 'functions.php';
}

// Include bootstrap autoloader
function core_autoloader()
{
	require_once CORE . 'class.Loader.php';
}
