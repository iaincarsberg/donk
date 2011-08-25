<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Test the doctrine implementation of the auth module.
 *
 * @author Iain Carsberg
 * @group modules.donk
 * @group modules.donk.auth
 * @group modules.donk.auth.auto_login
 **/
class Auth_Donk_Auto_LoginTest extends DONK_PHPUnit_Framework_Testcase
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
			array('user1', 'password', 1),
			array('user2', 'password', 2),
			array('user3', 'password', 3)
			);
	}
	
	/**
	 * Used to test users that is marked as being logged in.
	 * @dataProvider fixturedUsers
	 */
	public function testAuto_Login($username, $password, $user_id)
	{
		$auth = Auth::instance();
		
		// Makesure we have no tokens in the DB
		$tokens = Doctrine::getTable('Model_User_Token')->findAll();
		$this->assertSame(count($tokens), 0);
		
		$auth->login($username, $password, TRUE);
		
		// Makesure we now have a token.
		$tokens = Doctrine::getTable('Model_User_Token')->findAll();
		$this->assertSame(count($tokens), 1);
		
		$this->assertSame((string)$tokens[0]->user_id, (string)$user_id);
	}
}
