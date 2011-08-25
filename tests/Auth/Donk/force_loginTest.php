<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Test the doctrine implementation of the auth module.
 *
 * @author Iain Carsberg
 * @group modules.donk
 * @group modules.donk.auth
 * @group modules.donk.auth.force_login
 **/
class Auth_Donk_Force_loginTest extends DONK_PHPUnit_Framework_Testcase
{
	/**
	 * Called before each test
	 * @param void
	 * @return void
	 */
	public function setUp()
	{
		$this->prepare_tables(
			'Model_User', 
			'Model_Role', 
			'Model_Roles_Users'
			);
	}
	
	public function fixturedUsers()
	{
		return array(
			array('user1', TRUE),
			array('user2', TRUE),
			array('user3', TRUE),
			array('user4', TRUE),
			array('user5', FALSE),
			array('user6', FALSE)
			);
	}
	
	/**
	 * Test to see if we can force a fake login
	 * @dataProvider fixturedUsers
	 */
	public function testForceLogin($username, $exists)
	{
		$auth = Auth::instance();
		
		$this->assertSame($auth->force_login($username), $exists);
	}
}