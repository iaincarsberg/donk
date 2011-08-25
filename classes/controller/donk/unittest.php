<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Controller_Donkmin_UnitTest
 *
 * @package Donkmin
 * @author Iain Carsberg
 **/
class Controller_Donk_UnitTest extends Controller_UnitTest
{
	/**
	 * We want to connect to a unit testing database, where it doesn't matter 
	 * if we utterly destroy anything.
	 * @param void
	 * @return void
	 */
	public function before()
	{
		define('UNITTEST', TRUE);
		define('DONK_BUILDING_DATABASES', TRUE);
		
		set_time_limit(0);
		ini_set('memory_limit', '512M');
		
		// Instantiate Donk.
		Donk::instantiate('unittest');
		
		parent::before();
	}
	
	/**
	 * Assigns the template [View] as the request response.
	 */
	public function after()
	{
		$this->template->stats = View::factory('profiler/stats')->render();
		
		return parent::after();
	}
}
