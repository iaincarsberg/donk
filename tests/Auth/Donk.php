<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Test the doctrine implementation of the auth module.
 *
 * @author Iain Carsberg
 * @group modules.donk
 * @group modules.donk.auth
 **/
class Auth_DonkTest extends DONK_PHPUnit_Framework_Testcase
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
	
	/**
	 * Provides a list of users that are within the fixtures.
	 * @param void
	 * @return void
	 */
	public function fixturedUsers()
	{
		return array(
			array('user1', 'user1@horrain.com', 'password'),
			array('user2', 'user2@horrain.com', 'password'),
			array('user3', 'user3@horrain.com', 'password'),
			array('user4', 'user4@horrain.com', 'password')
			);
	}
	
	/**
	 * Used to test users that should be in the fixtures table
	 * @dataProvider fixturedUsers
	 */
	public function testUserExists($username, $email, $password)
	{
		$user = Doctrine::getTable('Model_User')->findOneByUsername($username);
		$auth = Auth::instance();
		
		$this->assertSame(get_class($user), 'Model_User');
		$this->assertSame($user->username, $username);
		$this->assertSame($user->email, $email);
		$this->assertSame($user->password, $auth->hash($password));
	}
}
