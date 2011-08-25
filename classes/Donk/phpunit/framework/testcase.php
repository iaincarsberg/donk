<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Donkmin_PHPUnit_Framework_Testcase
 *
 * @package Donkmin
 * @author Iain Carsberg
 **/
class DONK_PHPUnit_Framework_Testcase extends PHPUnit_Framework_TestCase
{
	private static $importer = FALSE;
	
	/**
	 * Used to execute any functions called beforeTestName
	 * @param void
	 * @return void
	 */
	public function execute_before()
	{
		if (strtolower(substr($this->name, 0, 4)) === 'test') {
			$method = sprintf('before%s', $this->name);
			if (method_exists($this, $method)) {
				$this->$method();
			}
		}
	}
	
	/**
	 * Used to make sure the table is prepared ready for testing.
	 * @param vararg Containing a list of tables
	 * @return void
	 */
	public function prepare_tables()
	{
		// A port of 'Doctrine-1.2.4/tests/DoctrineTest/Doctrine_UnitTestCase.php ::prepareTables'
		$conn = Doctrine_Manager::connection();
		
		// Some of the actions we need to do in here will break if foreign key
		// checks are enabled, so we need to disable them, prepare the tables
		// then reenable before ending.
		Doctrine_Manager::connection()->execute("
			SET FOREIGN_KEY_CHECKS = 0
		");
		
		$tables = array();
		foreach (func_get_args() as $arg) {
			if (! class_exists($arg)) {
				throw new Exception(sprintf("Unknown class '%s'.", $arg));
				continue;
			}
			$tables[] = $arg;
			
			// We want to reset the auto increment to 1, and then drop the table
			// so that we can insert fresh data.
            try {
            	$table = $conn->getTable($arg);
            	$conn->exec(sprintf(
					'ALTER TABLE %s AUTO_INCREMENT = 1;',
					$table->getTableName(),
					$table->getTableName()
					));
                $conn->exec('DROP TABLE ' . $table->getTableName());
            } catch(Doctrine_Connection_Exception $e) {
				
            }
		}
		
		$conn->export->exportClasses($tables);
        // $this->objTable = $this->connection->getTable('User');
		
		
		// Prep the singleton fixture list.
		if (! self::$importer) {
			$paths = array_reverse(
				array_values(Donk::get_file_list('Donk::contains_doctrine_fixture', 'config/fixture/'))
				);
				
			self::$importer = new Doctrine_Data_Import($paths);
	        self::$importer->setFormat('yml');
	        self::$importer->setModels(array());
		}
		
		//Doctrine::loadData(self::$fixtureList);
		self::$importer->doImport(false);
		
		// reenable foreign key checks before ending this function.
		Doctrine_Manager::connection()->execute("
			SET FOREIGN_KEY_CHECKS = 1
		");
		
		Session::instance()->destroy();
	}
}
