<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Test the doctrine implementation of the auth module.
 *
 * @author Iain Carsberg
 * @group modules.donk
 * @group modules.donk.auth
 * @group modules.donk.auth.login
 **/
class Auth_Donk_LoginTest extends DONK_PHPUnit_Framework_Testcase
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
	
	public function fixturedUsersBothValidAndInvalid()
	{
		return array(
			array('user1', 'password', TRUE),
			array('user2', 'password', TRUE),
			array('user3', 'password', TRUE),
			array('user4', 'password', FALSE),// is a user, but has no login
			array('user5', 'password', FALSE)
			);
	}
	
	/**
	 * Used to test users that is marked as being logged in.
	 * @dataProvider fixturedUsersBothValidAndInvalid
	 */
	public function testUserLogIn($username, $password, $should_succeed)
	{
		$auth = Auth::instance();
		
		$this->assertSame(
			$auth->login($username, $password),
			$should_succeed
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
	public function testUserLogInLocalVars($username, $password)
	{
		$auth = Auth::instance();
		
		for ($i=1; $i <= 5; $i++) { 
			$auth->login($username, $password);
			$user = $auth->get_user();
			
			$this->assertSame((string)$user->last_login, (string)time());
			$this->assertSame($user->logins, $i);
		}
	}
	
	public function fixturedUsersToTestAccountLocking()
	{
		return array(
			array('user1', 'fake_password', Kohana::$config->load('donk_auth.max_failed_login_attempts'), 'password'),
			array('user2', 'fake_password', Kohana::$config->load('donk_auth.max_failed_login_attempts'), 'password'),
			array('user3', 'fake_password', Kohana::$config->load('donk_auth.max_failed_login_attempts'), 'password')
		);
	}
	
	/**
	 * Used to see if multiple failed login attempts correctly executes 
	 * code that revokes the login role.
	 * @dataProvider fixturedUsersToTestAccountLocking
	 */
	public function testAccountLockingOnMultipleFails($username, $fake_password, $failed_login_attempts, $real_password)
	{
		$auth = Auth::instance();
		$mock = $this->getMock('Mock_Callback', array('call_me'));
		
		$mock->expects(
				$this->once()
			)
			->method('call_me')
			->with($this->equalTo('donk.auth.user.max_failed_login_attempts_met'), $this->isInstanceOf('Dispatcher_Event'))
			->will($this->returnValue(TRUE));
		
		DONK_Dispatcher::reset();
		DONK_Dispatcher::instance()->register_listener(
			'donk.auth.user.max_failed_login_attempts_met',
			array($mock, 'call_me')
			);
		
		// Execute the a number of invalid login attemps, which should cause 
		// the account to become locked.
		for ($i=1; $i <= $failed_login_attempts; $i++) {
			$this->assertFalse($auth->login($username, $fake_password));
			$this->assertFalse($auth->get_user());
		}
		
		// The account should have been nuked by now.
		$this->assertFalse($auth->login($username, $real_password));
		$this->assertFalse($auth->get_user());
	}
	
	public function fixturedUsersWithThereRoles()
	{
		return array(
			array('user1', 'password', TRUE, array('login','admin','dev')),
			array('user2', 'password', TRUE, array('login','admin')),
			array('user3', 'password', TRUE, array('login')),
			array('user4', 'password', FALSE, FALSE)
		);
	}
	
	/**
	 * Used to test users that should be in the fixtures table
	 * @dataProvider fixturedUsersWithThereRoles
	 */
	public function testUserLogInWithRoles($username, $password, $expectsArray, $roles)
	{
		$auth = Auth::instance();
		$auth->login($username, $password);
		$user = $auth->get_user();
		
		if ($expectsArray) {
			$owned_roles = 0;
			foreach ($user->Roles as $role) {
				$this->assertTrue(in_array($role->Role->name, $roles));
				$owned_roles += 1;
			}
			$this->assertSame($owned_roles, count($roles));
			
		} else {
			$this->assertFalse($user);
		}
	}
	
	/**
	 * Used to see if the token is created, and the cookie set
	 * @dataProvider fixturedUsersBothValidAndInvalid
	 */
	public function testUserLoginWithRemember($username, $password, $should_succeed)
	{
		$auth = Auth::instance();
		
		// Makesure we have no tokens in the DB
		$tokens = Doctrine::getTable('Model_User_Token')->findAll();
		$this->assertSame(count($tokens), 0);
		
		$this->assertSame(
			$auth->login($username, $password, TRUE),
			$should_succeed
			);
		
		if ($should_succeed) {
			// Makesure we now have a token.
			$tokens = Doctrine::getTable('Model_User_Token')->findAll();
			$this->assertSame(count($tokens), 1);
			
			$user = $auth->get_user();
			
			$this->assertSame(count($user->Tokens), 1);
			$this->assertSame($user->Tokens[0]->user_id, $user->id);
			$this->assertSame($user->Tokens[0]->created, (string)time());
			$this->assertSame($user->Tokens[0]->expires, (string)(time() + Kohana::$config->load('auth.lifetime')));
		}
	}
}
