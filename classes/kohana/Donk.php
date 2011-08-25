<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Kohana_Donk
 * This is the launcher for donk.
 *
 * @package Donk
 * @author Iain Carsberg
 * @twitter iaincarsberg
 **/
class Kohana_Donk
{
	/**
	 * Contains a list of found paths, used when Kohana::$caching is enabled
	 * @var array
	 */
	protected static $_paths = array();
	
	/**
	 * Used to make sure the instantiation process only happens once.
	 * @var boolean
	 **/
	private static $instantiate = FALSE;
	
	/**
	 * Starts the ball rolling.
	 * @param void
	 * @return void
	 */
	public static function instantiate($config_name = 'default')
	{
		// Make sure this code is only ran once.
		if (self::$instantiate === TRUE) {
			return;
		}
		self::$instantiate = TRUE;
		
		// Set the DONK_BUILDING_DATABASES flag if we're visiting the 'doctrine/generate' page
		if (! defined('DONK_BUILDING_DATABASES') AND
			isset($_SERVER['REQUEST_URI']) AND 
			strpos(ltrim($_SERVER['REQUEST_URI'], '/'), 'doctrine/generate') !== FALSE
		) {
			define('DONK_BUILDING_DATABASES', TRUE);
			
		} elseif (! defined('DONK_BUILDING_DATABASES')) {
			define('DONK_BUILDING_DATABASES', FALSE);
		}

		// Add the Doctrine library into Kohana
		Donk::autoload(DONK_BUILDING_DATABASES);

		// Connect to the database
		list($manager, $connection) = array_pad(Donk::connect($config_name), 2, FALSE);

		// Check to see if we have a database connection, if we don't then there isnt
		// much that we can do, other than exit that is.
		if ( ! $manager OR ! $connection) {
			exit('Could not establish database connection');
		}

		// Customise the connection: set attributed, apply listeners etc
		Donk::customise($manager, $connection);
	}
	
	/**
	 * Includes Doctrine into the Kohana namespace
	 * @param void
	 * @return void
	 */
	public static function autoload($use_verbose = TRUE)
	{
		if ($use_verbose) {
			require Kohana::find_file('vendor', 'Doctrine-1.2.4/lib/Doctrine');
			
		} else {
			require Kohana::find_file('vendor', 'Doctrine.compiled');
		}
		
		spl_autoload_register(array('Doctrine', 'autoload'));
	}
	
	/**
	 * Connects to the live database
	 * @param string $config_name Contains the default database connection 
	 * to be used.
	 * @return void
	 */
	public static function connect($config_name)
	{
		// Getting kohana configurations for doctrine
		$db = Kohana::$config->load('donk');
		
		// Makesure the parsed connection name exists.
		if (! array_key_exists($config_name, $db)) {
			throw new Exception(sprintf(
				"Cannot find '%s' in config.donk",
				$config_name
				));
		}
		
		// initializing manager
		$manager = Doctrine_Manager::getInstance();
		
		if ( ! defined('SUPPRESS_REQUEST') AND array_key_exists($_SERVER['SERVER_NAME'], $db)) {
			$config_name = $_SERVER['SERVER_NAME'];
		}
		
		$db_values = $db[$config_name];
		// we load our database connections into Doctrine_Manager
		// this loop allows us to use multiple connections later on
		if (
			!array_key_exists('type', 		$db[$config_name]) ||
			!array_key_exists('connection', $db[$config_name]) ||
			!array_key_exists('username', 	$db[$config_name]['connection']) ||
			!array_key_exists('password', 	$db[$config_name]['connection']) ||
			!array_key_exists('hostname', 	$db[$config_name]['connection']) ||
			!array_key_exists('database', 	$db[$config_name]['connection'])
		) {
			exit('Invalid database config.');
		}

		// first we must convert to dsn format
		$dsn = sprintf(
			"%s://%s:%s@%s/%s",
			$db[$config_name]['type'],
			$db[$config_name]['connection']['username'],
			$db[$config_name]['connection']['password'],
			$db[$config_name]['connection']['hostname'],
			$db[$config_name]['connection']['database']
		);
		
		$connection = Doctrine_Manager::connection($dsn, $config_name);
		
		self::load_models();
		
		$profile_connection = Profiler::start("Doctrine", 'connect to live database');
		Profiler::stop($profile_connection);
		
		return array(
			$manager,
			$connection
		);
	}
	
	/**
	 * Customised the connection
	 * @param void
	 * @return void
	 */
	public static function customise($manager, $connection)
	{
		// this will allow us to use "mutators"
		$manager->setAttribute(Doctrine_Core::ATTR_AUTO_ACCESSOR_OVERRIDE, TRUE );
		// Automatically free queries
		$manager->setAttribute(Doctrine_Core::ATTR_AUTO_FREE_QUERY_OBJECTS, TRUE );
		
		/*
		// Enable validation
		//$manager->setAttribute(Doctrine::ATTR_VALIDATE, Doctrine::VALIDATE_ALL);
		// this sets all table columns to notnull and unsigned (for ints) by default
		$manager->setAttribute(
			Doctrine_Core::ATTR_DEFAULT_COLUMN_OPTIONS,
			array('notnull' => TRUE, 'unsigned' => TRUE)
		);
		*/
		
		// set the default primary key to be named 'id', integer, 4 bytes
		/*
		$manager->setAttribute(
			Doctrine_Core::ATTR_DEFAULT_IDENTIFIER_OPTIONS,
			array('name' => 'id', 'type' => 'integer', 'length' => 4)
		);
		*/
		
		// Link Doctrine to Kohana's profiler.
		$connection->setListener(Model::factory('doctrine_profile'));
	}
	
	/**
	 * Used to load the models into memory
	 * @param void
	 * @return void
	 */
	protected static function load_models()
	{
		// If we're building the databases we don't want to load any of it 
		// into memory.
		// Unless we're unit testing, then we do.
		if ((defined('DONK_BUILDING_DATABASES') AND DONK_BUILDING_DATABASES) AND
			! defined('UNITTEST')
		) {
			return;
		}
		
		// telling Doctrine where our models are located
		Doctrine::loadModels(
			array_values(Donk::get_file_list('Donk::contains_doctrine_generated', 'classes/model/generated/'))
			);
		Doctrine::loadModels(
			array_values(Donk::get_file_list('Donk::contains_model_directory', 'classes/model/'))
			);
	}
	
	/**
	 * Used to execute and fixtures that set test data ready for the unittests
	 * to process.
	 * @param void
	 * @return void
	 */
	public static function execute_fixtures()
	{
		// We reverse the array so that the APPPATH fixture array is at 
		// the bottom of the pile, this means that if any of the fixture 
		// files alter the default values set within the modules they will
		// take presidence and replace the defaults.
		Doctrine::loadData(
			array_reverse(array_values(Donk::get_file_list('Donk::contains_doctrine_fixture', 'config/fixture/')))
			);
	}
	
	/**
	 * Used to get a list of active modules with doctrine schema files present
	 * @param string $filter Called to provide filtering to the module list.
	 * @param string $export_suffix Added to the end of the return url.
	 * @return array Containing a key value pair array of modules and paths.
	 */
	public static function get_file_list($filter = FALSE, $export_suffix = FALSE)
	{
		// Used in tandem with Kohana::$caching.
		$key = ($filter?$filter:'FALSE').'-'.($export_suffix?$export_suffix:'FALSE');
		
		if (Kohana::$caching === TRUE AND isset(Donk::$_paths[$key]))
		{
			// This path has been cached
			return Donk::$_paths[$key];
		}
		
		$sections = array();
		
		// Mixing the APPPATH as a possible target location.
		$modules = array_merge(
			array(''=> APPPATH), 
			Kohana::modules()
			);
		
		foreach ($modules as $module => $path) {
			if ( ! call_user_func_array($filter, array($path))) {
				continue;
			}
			
			$sections[strtolower($module)] = $path.$export_suffix;
		}
		
		if (Kohana::$caching === TRUE)
		{
			// This path has been cached
			Donk::$_paths[$key] = $sections;
		}
		
		return $sections;
	}
	
	/**
	 * Used to see if doctrine schema information is present
	 * @param string $path Contains the path to the module to see if they 
	 *        contain valid schema information.
	 * @return boolean
	 */
	public static function contains_doctrine_schemas($path)
	{
		// Makesure there is a schema config directory
		if (is_dir($path . '/config/schema')) {
			// Makesure it has yml files inside it.
			foreach (scandir($path . '/config/schema') as $file) {
				if (strpos($file, '.yml') !== FALSE) {
					return TRUE;
				}
			}
		}
		return FALSE;
	}
	
	/**
	 * Used to list all schemas within a target directory
	 * @param string $path Contains the path to the module to see if they 
	 *        contain valid schema information.
	 * @return boolean
	 */
	public static function list_doctrine_schemas($path)
	{
		$schemas = array();
		// Makesure there is a schema config directory
		if (is_dir($path . '/config/schema')) {
			// Makesure it has yml files inside it.
			foreach (scandir($path . '/config/schema') as $file) {
				if (strpos($file, '.yml') !== FALSE) {
					$schemas[] = $file;
				}
			}
		}
		return $schemas;
	}
	
	/**
	 * Used to see if doctrine fixture information is present
	 * @param string $path Contains the path to the module to see if they 
	 *        contain valid fixture information.
	 * @return boolean
	 */
	public static function contains_doctrine_fixture($path)
	{
		if (is_dir($path . '/config/fixture')) {
			return TRUE;
		}
		return FALSE;
	}
	
	/**
	 * Used to see if doctrine generated classes exist
	 * @param string $path Contains the path to the module to see if they 
	 *        contain doctrine models.
	 * @return boolean
	 */
	public static function contains_doctrine_generated($path)
	{
		if (is_dir($path . '/classes/model') AND is_dir($path . '/classes/model/generated')) {
			return TRUE;
		}
		return FALSE;
	}
	
	/**
	 * Used to see if a model directory exists in the module
	 * @param string $path Contains the path to the module to see if they 
	 *        contain doctrine models.
	 * @return boolean
	 */
	public static function contains_model_directory($path)
	{
		if (is_dir($path . '/classes/model')) {
			return TRUE;
		}
		return FALSE;
	}
}