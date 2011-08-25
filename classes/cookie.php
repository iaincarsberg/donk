<?php defined('SYSPATH') or die('No direct script access.');

class Cookie extends Kohana_Cookie {
	/**
	 * Applies a config file to the cookie
	 * @param void
	 * @return void
	 */
	public static function config()
	{
		self::$salt       = Kohana::$config->load('cookie.salt');
		self::$expiration = Kohana::$config->load('cookie.expiration');
		self::$path       = Kohana::$config->load('cookie.path');
		self::$domain     = Kohana::$config->load('cookie.domain');
		self::$secure     = Kohana::$config->load('cookie.secure');
		self::$httponly   = Kohana::$config->load('cookie.httponly');
	}
}