<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Test the doctrine implementation of the auth module.
 *
 * @author Iain Carsberg
 * @group modules.donk
 * @group modules.donk.auth
 * @group modules.donk.auth.logged_in
 **/
class Auth_Donk_Logged_inTest extends DONK_PHPUnit_Framework_Testcase
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
	public function testLogged_in($username, $password, $should_succeed)
	{
		$auth = Auth::instance();
		$auth->login($username, $password);
		
		$this->assertSame(
			$auth->logged_in(),
			$should_succeed
			);
	}
	
	public function fixturedUsersWithTheRolesTheyHaveAndDont()
	{
		return array(
			array('user1', 'password', array('login', 'admin', 'dev'), array()),
			array('user2', 'password', array('login', 'admin'), array('dev')),
			array('user3', 'password', array('login'), array('admin', 'dev')),
			array('user4', 'password', array(), array('login', 'admin', 'dev'))
		);
	}
	
	/**
	 * Used to test users that is marked as being logged in, and tests they
	 * have a set role.
	 * @dataProvider fixturedUsersWithTheRolesTheyHaveAndDont
	 */
	public function testLogged_inWithRole($username, $password, $owned_roles, $alien_roles)
	{
		$auth = Auth::instance();
		$auth->login($username, $password);
		
		foreach ($owned_roles as $name) {
			$this->assertTrue($auth->logged_in($name));
		}
		foreach ($alien_roles as $name) {
			$this->assertFalse($auth->logged_in($name));
		}
	}
	
	/**
	 * Used you can also use the logged_in function with an array of roles
	 * @dataProvider fixturedUsersWithTheRolesTheyHaveAndDont
	 */
	public function testLogged_inWithArrayOfRoles($username, $password, $owned_roles, $alien_roles)
	{
		$auth = Auth::instance();
		$auth->login($username, $password);
		
		if (count($owned_roles) > 0) {
			$this->assertTrue($auth->logged_in($owned_roles));
		}
		
		if (count($alien_roles) > 0) {
			$this->assertFalse($auth->logged_in($alien_roles));
		}
	}
}
