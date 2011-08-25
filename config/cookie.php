<?php defined('SYSPATH') or die('No direct access allowed.');

return array(
	/**
	 * @var  string  Magic salt to add to the cookie, generate from https://www.grc.com/passwords.htm
	 */
	'salt' => FALSE,//'...',

	/**
	 * @var  integer  Number of seconds before the cookie expires
	 */
	'expiration' => FALSE,//0,

	/**
	 * @var  string  Restrict the path that the cookie is available to
	 */
	'path' => FALSE,//'/',

	/**
	 * @var  string  Restrict the domain that the cookie is available to
	 */
	'domain' => FALSE,//NULL,

	/**
	 * @var  boolean  Only transmit cookies over secure connections
	 */
	'secure' => FALSE,

	/**
	 * @var  boolean  Only transmit cookies over HTTP, disabling Javascript access
	 */
	'httponly' => FALSE,
);