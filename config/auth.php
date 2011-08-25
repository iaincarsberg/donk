<?php defined('SYSPATH') or die('No direct access allowed.');

return array(
	
	'driver'       => 'donk',
	'hash_method'  => 'sha256',
	
	// https://www.grc.com/passwords.htm
	'hash_key'     => '5B3732591F2935E65D7C3D87B14D8C269777D30343B5274FD9DA40FD83D6FA9D',
	'lifetime'     => 1209600,
	'use_session'  => TRUE,
	'session_key'  => 'auth_user',

	// Username/password combinations for the Auth File driver
	'users' => array(
		// 'admin' => 'b3154acf3a344170077d11bdb5fff31532f679a1919e716a02',
	),

);
