<?php
/*
	Core Functions
	Developer Name : Kimi
	Description : Main tools
*/

//	MANAGE STRINGS

if (!function_exists('strip_accents')) {

	function strip_accents ($str) {
		
		return strtr($str,'ΰαβγδηθικλμνξορςστυφωϊϋόύÿΐΑΒΓΔΗΘΙΚΛΜΝΞΟΡÒΣΤΥΦΩΪΫάέ','aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
	
	}
	
}


if (!function_exists('strip_specialchars')) {
	
	function strip_specialchars ($str, $replacement='') {
	
		$str = htmlentities(strip_tags($str));
		return preg_replace('/&.+;/', $replacement, $str);
		
	}
	
}


if (!function_exists('beautify')) {
	
	function beautify ($str) {
	
		$str = strip_specialchars($str, '-');
		$str = trim($str);
		$str = str_replace(' ', '-', $str);
		
		do {
			$str = str_replace('--', '-', $str, $r);
		}
		while ($r != 0);

		return strip_accents($str);
	
	}
	
}


if (!function_exists('is_email')) {

	function is_email ($str) {

		return preg_match('`([[:alnum:]]([-_.]?[[:alnum:]])*@[[:alnum:]]([-_.]?[[:alnum:]])*\.([a-z]{2,4}))`', $str);

	}

}

if (!function_exists('getperms')) {

	function getperms ($path) {
		return substr(sprintf('%o', fileperms($path)), -4);
	}

}

if (!function_exists('url_get_contents')) {

	function url_get_contents ($Url) {
		if (!function_exists('curl_init')){
			error_log('curl is not installed');
			return false;
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $Url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, true);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}

}

if (!function_exists('key_generator')) {

	function key_generator($length=8) {
		$alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
		$key = array();
		$alphaLength = strlen($alphabet) - 1;
		for ($i = 0; $i < $length; $i++)
		{
			$n = rand(0, $alphaLength);
			$key[] = $alphabet[$n];
		}
		return implode($key);
	}
	
}