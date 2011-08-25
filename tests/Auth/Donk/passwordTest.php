<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Test the doctrine implementation of the auth module.
 *
 * @author Iain Carsberg
 * @group modules.donk
 * @group modules.donk.auth
 * @group modules.donk.auth.password
 **/
class Auth_Donk_PasswordTest extends DONK_PHPUnit_Framework_Testcase
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
	 * Make sure the password function returns the correct password.
	 * @dataProvider fixturedUsers
	 */
	public function testStoredPassword($username, $password)
	{
		$auth = Auth::instance();
		$auth->login($username, $password);
		
		$this->assertSame(
			$auth->password($auth->get_user()),
			$auth->hash($password)
			);
	}
	
	public function fixturedUnknownUsers()
	{
		return array(
			array('unknown_user1', 'password', 'unknown_user1@example.com'),
			array('unknown_user2', 'password', 'unknown_user2@example.com'),
			array('unknown_user3', 'password', 'unknown_user3@example.com')
			);
	}
	
	/**
	 * Makes a bunch of new accounts, testing that the password is correctly 
	 * saved into the model.
	 * @dataProvider fixturedUnknownUsers
	 */
	public function testAccountCreationAndItsPasswordStoring($username, $password, $email)
	{
		$auth = Auth::instance();
		$login = Doctrine::getTable('Model_Role')->findOneByName('login');
		
		// Make a new account.
		$user = new Model_User();
		$user->username = $username;
		$user->password = $password;
		$user->email = $email;
		$user->Roles[]->Role = $login;
		$user->save();
		
		// Check its stored password
		$this->assertSame($user->password, $auth->hash($password));
		
		// And log into the account, and check the password matches via the
		// password() function.
		$auth->login($username, $password);
		$this->assertSame(
			$auth->password($auth->get_user()),
			$auth->hash($password)
			);
	}
	
	/**
	 * Contains a list of users that will have there passwords changed.
	 * @param void
	 * @return void
	 */
	public function fixturedUsersWithAlterations()
	{
		return array(
			array('user1', 'password', 'new_password'),
			array('user2', 'password', 'new_password'),
			array('user3', 'password', 'new_password')
		);
	}
	
	/**
	 * Tests to see if doing $user->password = '...'; changes it correctly.
	 * @dataProvider fixturedUsersWithAlterations
	 */
	public function testPasswordChangineWorks($username, $password, $new_password)
	{
		$auth = Auth::instance();
		$auth->login($username, $password);
		
		$user = $auth->get_user();
		$user->password = $new_password;
		$user->save();
		
		$this->assertSame(
			$user->password,
			$auth->hash($new_password)
			);
		
		// And log into the account, and check the password matches via the
		// password() function.
		$auth->login($username, $new_password);
		$this->assertSame(
			$auth->password($auth->get_user()),
			$auth->hash($new_password)
			);
	}
}
