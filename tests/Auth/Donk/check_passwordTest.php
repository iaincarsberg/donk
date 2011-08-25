<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Test the doctrine implementation of the auth module.
 *
 * @author Iain Carsberg
 * @group modules.donk
 * @group modules.donk.auth
 * @group modules.donk.auth.check_password
 **/
class Auth_Donk_Check_PasswordTest extends DONK_PHPUnit_Framework_Testcase
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
			'Model_Roles_Users', 
			'Model_User_Token'
			);
	}
	
	public function fixturedUsers()
	{
		return array(
			array('user1', 'password', 'password', TRUE),
			array('user2', 'password', 'password', TRUE),
			array('user3', 'password', 'password', TRUE),
			array('user1', 'password', 'fake-password', FALSE),
			array('user2', 'password', 'fake-password', FALSE),
			array('user3', 'password', 'fake-password', FALSE)
			);
	}
	
	/**
	 * Used to test users that is marked as being logged in.
	 * @dataProvider fixturedUsers
	 */
	public function testCheck_Password($username, $password, $check_with, $expected)
	{
		$auth = Auth::instance();
		$auth->login($username, $password);
		
		$this->assertSame($auth->check_password($check_with), $expected);
	}
}
