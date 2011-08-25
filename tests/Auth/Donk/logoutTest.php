<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Test the doctrine implementation of the auth module.
 *
 * @author Iain Carsberg
 * @group modules.donk
 * @group modules.donk.auth
 * @group modules.donk.auth.logout
 **/
class Auth_Donk_LogoutTest extends DONK_PHPUnit_Framework_Testcase
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
			array('user1', 'password'),
			array('user2', 'password'),
			array('user3', 'password')
			);
	}
	
	/**
	 * Used to test users that is marked as being logged in.
	 * @dataProvider fixturedUsers
	 */
	public function testLogout($username, $password)
	{
		$auth = Auth::instance();
		$auth->login($username, $password);
		
		$this->assertTrue($auth->logout());
		$this->assertFalse($auth->get_user());
	}
	
	/**
	 * Used to test users that were logged in with remember me enabled.
	 * @dataProvider fixturedUsers
	 */
	public function testLogoutWhenLoginWasRemembered($username, $password)
	{
		$auth = Auth::instance();
		
		// Makesure we have no tokens in the DB
		$tokens = Doctrine::getTable('Model_User_Token')->findAll();
		$this->assertSame(count($tokens), 0);
		
		$auth->login($username, $password, TRUE);
		
		// Makesure we now have a token.
		$tokens = Doctrine::getTable('Model_User_Token')->findAll();
		$this->assertSame(count($tokens), 1);
		
		// Fudge in a cookie value, otherwise the cookie will only exist in the
		// next request.
		Cookie::$salt = Kohana::config('cookie.salt');
		$_COOKIE['authautologin'] = Cookie::salt('authautologin', $tokens[0]->token).'~'.$tokens[0]->token;
		
		$this->assertTrue($auth->logout());
		$this->assertFalse($auth->get_user());
		
		$tokens = Doctrine::getTable('Model_User_Token')->findAll();
		$this->assertSame(count($tokens), 0);
		
	}
}
