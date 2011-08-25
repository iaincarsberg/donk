<?php defined('SYSPATH') or die('No direct script access.');

class Cookie extends Kohana_Cookie {
	/**
	 * Applies a config file to the cookie
	 * @param void
	 * @return void
	 */
	public static function config()
	{
		self::$salt       = Kohana::config('cookie.salt');
		self::$expiration = Kohana::config('cookie.expiration');
		self::$path       = Kohana::config('cookie.path');
		self::$domain     = Kohana::config('cookie.domain');
		self::$secure     = Kohana::config('cookie.secure');
		self::$httponly   = Kohana::config('cookie.httponly');
	}
}