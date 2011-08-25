<?php defined('SYSPATH') or die('No direct access allowed.');

// Exceptions
class DONK_Exception extends Exception{}

// I've wrapped-up the cookie customisation stuff to it uses config/cookie.php
Cookie::config();

// Fixes an issue when moving to some live servers.
require Kohana::find_file('classes/kohana', 'Donk');
require Kohana::find_file('classes', 'Donk');

// If the unitest module exists add a rewrite rule to serve a donk flavoured
// version instead.
if (array_key_exists('unittest', Kohana::modules())) {
	Route::set('unitest', 'unittest(/<action>(/<id>))')
		->defaults(array(
			'directory'  => 'donk',
			'controller' => 'unittest',
			'action'     => 'index',
		));
}