<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * DONK Auth driver.
 *
 * @package    DONKmin
 * @author     Iain Carsberg
 * @copyright  (c) 2007-2011 Kohana Team
 * @license    http://kohanaframework.org/license
 */
class Kohana_Auth_DONK extends Auth {
	/**
	 * Flags if we're using the session or not.
	 * @var boolean
	 **/
	protected $_use_session;
	
	/**
	 * Loads Session and configuration options.
	 *
	 * @return  void
	 */
	public function __construct($config = array())
	{
		// Save the config in the object
		$this->_config = $config;
		
		// Localise the use_session config value.
		$this->_use_session = $this->_config['use_session'];
		
		// If we're using the session bind it.
		if ($this->_use_session) {
			$this->_session = Session::instance();
		}
	}
	
	/**
	 * Checks if a session is active.
	 *
	 * @param   mixed    $role Role name string, role DONK object, or array with role names
	 * @return  boolean
	 */
	public function logged_in($role = NULL)
	{
		// Get the user from the session
		if ($this->_use_session) {
			$user = $this->get_user();
			
		// If we're not using the session check for an auto_login cookie.
		} else {
			$user = $this->auto_login();
		}

		if ( ! $user) {
			return FALSE;
		}

		if ($user instanceof Model_User)
		{
			// If we don't have a roll no further checking is needed
			if ( ! $role) {
				return TRUE;
			}
			
			if (is_array($role))
			{
				// Get all the roles
				$roles = Doctrine_Query::create()
					->from('Model_Role r')
					->where('r.name IN ?', array($role))
					->execute();
				
				// Make sure all the roles are valid ones
				if (count($roles) !== count($role))
					return FALSE;
			}
			else
			{
				if ( ! is_object($role))
				{
					// Load the role
					$roles = Doctrine::getTable('Model_Role')
						->findByName($role);
					
					if ( ! $roles OR count($roles) === 0)
						return FALSE;
				}
			}
			
			return $user->has_role($roles);
		}
		return FALSE;
	}

	/**
	 * Logs a user in.
	 *
	 * @param   string   username
	 * @param   string   password
	 * @param   boolean  enable autologin
	 * @return  boolean
	 */
	protected function _login($user, $password, $remember)
	{
		if ( ! is_object($user))
		{
			$username = $user;
			
			$user = Model_User::fetchByUsername($username);
			
			// If no user was returned
			if (! $user) {
				return FALSE;
			}
		}
		
		// If we're not using the session then we're using cookies, so we need
		// to force the remember flag.
		if (! $this->_use_session) {
			$remember = TRUE;
		}
		
		// If the passwords match, perform a login
		if ($user->has_role('login') AND $user->password === $password)
		{
			if ($remember === TRUE)
			{
				// Token data
				$token = new Model_User_Token();
				$token->User = $user;
				$token->user_agent = sha1(Request::$user_agent);
				$token->expires = time() + $this->_config['lifetime'];
				$token->save();
				
				// Set the autologin cookie
				Cookie::set('authautologin', $token->token, $this->_config['lifetime']);
			}
			
			// Finish the login
			$this->complete_login($user);

			return TRUE;
			
		} else {
			$user->invalid_login();
		}

		// Login failed
		return FALSE;
	}

	/**
	 * Forces a user to be logged in, without specifying a password.
	 *
	 * @param   mixed    username string, or user DONK object
	 * @param   boolean  mark the session as forced
	 * @return  boolean
	 */
	public function force_login($user, $mark_session_as_forced = FALSE)
	{
		// Fetch a user
		if (! ($user instanceof Model_User)) {
			$user = Model_User::fetchByUsernameOrEmail($user);
			if (! $user) {
				return FALSE;
			}
		}
		
		if ($mark_session_as_forced === TRUE) {
			// Mark the session as forced, to prevent users from changing account information
			$this->_session->set('auth_forced', TRUE);
		}
		
		// Run the standard completion
		$this->complete_login($user);
		return TRUE;
	}

	/**
	 * Logs a user in, based on the authautologin cookie.
	 *
	 * @return  mixed
	 */
	public function auto_login()
	{
		// If we're not using sessions, then we need to fake the auto_login
		// process, by using a singleton inside the user model.
		if (! $this->_use_session AND 
			($user = Model_User::auto_login())
		) {
			return $user;
		}
		
		if ($token = Cookie::get('authautologin'))
		{
			// Load the token and user
			$token = Doctrine_Query::create()
				->from('Model_User_Token t, t.User u')
				->where('t.token=?', $token)
				->fetchOne();
			
			if ($token AND $token->User)
			{
				if ($token->user_agent === sha1(Request::$user_agent))
				{
					// Save the token to create a new unique token
					$token->save();

					// Set the new token
					Cookie::set('authautologin', $token->token, $token->expires - time());

					// Complete the login with the found data
					$this->complete_login($token->User);
					
					// If we're not using sessions, then we need to set the 
					// auto login user, otherwise the token regeneration
					// process will void additional calls to auto_login.
					if (! $this->_use_session) {
						Model_User::auto_login($token->User);
					}

					// Automatic login was successful
					return $token->User;
				}

				// Token is invalid
				$token->delete();
			}
		}

		return FALSE;
	}

	/**
	 * Gets the currently logged in user from the session (with auto_login check).
	 * Returns FALSE if no user is currently logged in.
	 *
	 * @return  mixed
	 */
	public function get_user($default = NULL)
	{
		$user = FALSE;
		if ($this->_use_session) {
			$user = parent::get_user($default);
		}

		if ( ! $user)
		{
			// check for "remembered" login
			$user = $this->auto_login();
		}

		return $user;
	}

	/**
	 * Log a user out and remove any autologin cookies.
	 *
	 * @param   boolean  completely destroy the session
	 * @param	boolean  remove all tokens for user
	 * @return  boolean
	 */
	public function logout($destroy = FALSE, $logout_all = FALSE)
	{
		// Set by force_login()
		if ($this->_use_session) {
			$this->_session->delete('auth_forced');
		}
		
		if ($token = Cookie::get('authautologin'))
		{
			// Delete the autologin cookie to prevent re-login
			Cookie::delete('authautologin');
			
			// Clear the autologin token from the database
			$token = Doctrine_Query::create()
				->from('Model_User_Token t, t.User u')
				->where('t.token=?', $token)
				->fetchOne();
			
			if ($token AND $logout_all) {
				Doctrine_Query::create()
					->delete('t')
					->from('Model_User_Token t')
					->where('t.user_id=?', $token->User->id)
					->execute();
				
			} elseif ($token) {
				$token->delete();
			}
		}
		
		// If we're using the session then log us out normally
		if ($this->_use_session) {
			return parent::logout($destroy);
			
		// If we're not just double check we're destroyed.
		} else {
			// Double check
			return ! $this->logged_in();
		}
	}

	/**
	 * Get the stored password for a username.
	 *
	 * @param   mixed   username string, or user DONK object
	 * @return  string
	 */
	public function password($user)
	{
		// If the user isn't a Model_User, then try and make it one.
		if (! ($user instanceof Model_User)) {
			$user = Doctrine::getTable('Model_User')
				->findOneByUsername($user);
		}
		
		// If the user still isn't a Model_User then its invalid.
		if (! ($user instanceof Model_User)) {
			return FALSE;
		}
		
		return $user->password;
	}

	/**
	 * Complete the login for a user by incrementing the logins and setting
	 * session data: user_id, username, roles.
	 *
	 * @param   object  user DONK object
	 * @return  void
	 */
	protected function complete_login($user)
	{
		$user->complete_login();
		
		if ($this->_use_session) {
			return parent::complete_login($user);
		} else {
			return TRUE;
		}
	}

	/**
	 * Compare password with original (hashed). Works for current (logged in) user
	 *
	 * @param   string  $password
	 * @return  boolean
	 */
	public function check_password($password)
	{
		$user = $this->get_user();

		if ( ! $user)
			return FALSE;

		return ($this->hash($password) === $user->password);
	}

} // End Auth DONK