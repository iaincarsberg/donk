<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Test the doctrine implementation of the auth module.
 *
 * @author Iain Carsberg
 * @group modules.donk
 * @group modules.donk.auth
 * @group modules.donk.auth.get_user
 **/
class Auth_Donk_Login_Get_userTest extends DONK_PHPUnit_Framework_Testcase
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
			array('user1', 'password'),
			array('user2', 'password'),
			array('user3', 'password')
			);
	}
	
	/**
	 * Used to test users that is marked as being logged in.
	 * @dataProvider fixturedUsers
	 */
	public function testUserLogIn($username, $password)
	{
		$auth = Auth::instance();
		$auth->login($username, $password);
		
		$this->assertSame(
			$auth->get_user()->username,
			$username
			);
		$this->assertSame(
			$auth->get_user()->password,
			$auth->hash($password)
			);
	}
}
