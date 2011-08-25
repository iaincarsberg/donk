<?php defined('SYSPATH') or die('No direct script access.'); 

class Controller_Doctrine extends Controller
{
	public function before()
	{
		parent::before(); 

		//Restrict controller to localhost
		if ( !in_array($_SERVER['REMOTE_ADDR'], Kohana::config('donk.admin_ip_list')) )
		{
			echo "DENIED!";
			exit;
		}
	}
	
	public function after()
	{
		parent::after();
		
		echo View::factory('profiler/stats')->render();
	}
	
	public function action_index()
	{
		echo Html::anchor('doctrine/generate', 'Generate!');
	}
	
	public function action_generate($config = 'default')
	{
		echo sprintf('<h1>Building tables into the %s config.</h1>', $config);
		$this->list_tables();
		Donk::instantiate($config);
		
		?>
		Regenerate models from schema, for the selected module.<br />
		<form action="" method="POST">
			<input type="submit" name="schema" value="Load schema"><br /><br />
		
		Rebuilds the database, this will nuke all persistent content, and rebuild all tables in all active modules.<br />
		<form action="" method="POST">
			<input type="submit" name="action" value="Create all tables"><br /><br />
			
		This will repopulate the database after it was rebuilt, by executing all fixtures in all active modules.<br />
		<form action="" method="POST">
			<input type="submit" name="data" value="Load all fixtures"><br /><br />
		<?php
		$did = false;
		if ( !empty($_POST['schema']) )
		{
			// Grabs a list of all moduels that contain doctrine schemas.
			$list = Donk::get_file_list('Donk::contains_doctrine_schemas');
			
			// Combine all active module schema files into one massive schema,
			// this allows relationships between modules to be correctly form.
			$tempSchema = $this->create_temp_schema($list);
			
			// Once we have combined our schemas into one massive schema we 
			// need to set the path for the models to be generated into. This
			// allows us then process the generated models into the correct 
			// moduel hirarachy.
			$tempModels = sprintf(
				'%sdonk/temp/models',
				MODPATH
				);
			
			// Generate the temp schema into the temp models directory.
			Doctrine_Core::generateModelsFromYaml(
				$tempSchema,
				$tempModels,
				array(
					'classPrefix'=> 'Model_',
					'classPrefixFiles'=> false,
					'baseClassName'=> 'Model',
					'pearStyle'=> true
				)
			);
			
			// Move the temp models out into the module that created them.
			$this->move_temp_models_to_modules($tempModels, $list);
			
			// Clean up the temp models directory.
			$this->tidy_up_temp_directory(sprintf(
				'%sdonk/temp',
				MODPATH
				), true);
			
			$did = 'load schemas';
		}
		elseif ( !empty($_POST['action']) )
		{
			Doctrine::loadModels(
				array_values(Donk::get_file_list('Donk::contains_doctrine_generated', 'classes/model/generated/'))
				);
			Doctrine::loadModels(
				array_values(Donk::get_file_list('Donk::contains_model_directory', 'classes/model/'))
				);
			Doctrine::dropDatabases();
			Doctrine::createDatabases();
			Doctrine::createTablesFromModels();
			
			$did = 'create tables';
		}
		elseif ( !empty($_POST['data']) )
		{
			Doctrine::loadModels(
				array_values(Donk::get_file_list('Donk::contains_doctrine_generated', 'classes/model/generated/'))
				);
			Doctrine::loadModels(
				array_values(Donk::get_file_list('Donk::contains_model_directory', 'classes/model/'))
				);
			Doctrine_Manager::connection()->execute("
				SET FOREIGN_KEY_CHECKS = 0
			"); 
			
			// We reverse the array so that the APPPATH fixture array is at 
			// the bottom of the pile, this means that if any of the fixture 
			// files alter the default values set within the modules they will
			// take presidence and replace the defaults.
			Doctrine::loadData(
				array_reverse(array_values(Donk::get_file_list('Donk::contains_doctrine_fixture', 'config/fixture/')))
				);
			$did = 'load fixtures';
		}
		echo "Done ".$did."! ".date('G:i:s', time());
	}
	
	/**
	 * Used to create one massive schema, which allows inter-module
	 * relationships to be formed. The downside to this is that a ton of 
	 * additional models will get created, which will need unlinking.
	 * @param array $list Contains all active modules.
	 * @return string Containing the path to the temp schema file.
	 */
	private function create_temp_schema($list)
	{
		// Point to the temp schema directory, we need to see if it exists
		// before we can go copying the schema into it.
		$tmpPath = sprintf(
			'%sdonk/temp/schema',
			MODPATH
			);
		
		// If the temp path doesn't exist create it.
		if ( ! is_dir($tmpPath))
			mkdir($tmpPath);
		
		// Set the path to the temp file, this will be unlinked, and recreated
		// at build time.
		$tmpPath = sprintf(
			'%s/temp.yml',
			$tmpPath
			);
		
		// Remove the current temp schema file.
		if (is_file($tmpPath))
			unlink($tmpPath);
			
		// Ready the content to go in the new temp file.
		$content = '';
		
		// Contains a list of names that are loaded, this allows the 
		// application to replace schema files in remote modules.
		//
		// It also means that each schema file needs a quite name.
		$loaded = array();
		
		// Loop over the modules list and read in each file and append it to 
		// the content variable.
		foreach ($list as $module => $path) {
			// If the module doesn't have any schemas then skip over it.
			if ( ! Donk::contains_doctrine_schemas($path))
				continue;
			
			// Loop over each schema within a module, encase one module has
			// multiple schema files.
			foreach (Donk::list_doctrine_schemas($path) as $file) {
				// Makesure this file hasn't already been loaded.
				if (in_array($file, $loaded)) {
					Profiler::stop(
						Profiler::start(
							"Controller_Doctrine::create_temp_schema", 
							sprintf(
								'skipping "%s" in "%s" as it has already been loaded.', 
								$file,
								str_replace(DOCROOT, '', $path)
								))
						);
					continue;
				}
				
				$content .= file_get_contents(sprintf('%sconfig/schema/%s', $path, $file)) . "\n";
				$loaded[] = $file;
			}
		}
		
		// Insert the contents into a new temp file.
		file_put_contents(
			$tmpPath,
			$content
			);
		
		return $tmpPath;
	}
	
	/**
	 * Used to move the generated models out of the temporary directory and
	 * into their correct modules.
	 * @param string $dir Contains the path to the temporary models directory.
	 * @param array $modules Contains a list of active modules.
	 * @return void
	 */
	private function move_temp_models_to_modules($dir, $modules)
	{
		echo '<pre>';
		foreach (Donk_Filemover::execute($dir, $modules) as $file) {
			echo sprintf("Moved file to '%s'.\n", $file);
		}
		echo '</pre>';
	}
	
	/**
	 * Nukes all the files that were created during the loading of the schema
	 * @param string $dir Contains the path to nuke.
	 * @param boolean $keep_this Allows specific files/folders to be saved.
	 * @return boolean Used for recursion.
	 */
	private function tidy_up_temp_directory($dir, $keep_this=false)
	{
		// If the dir is a dud then do nothing
		if ( ! file_exists($dir))
			return true;
		
		// If the dir is pointing to a file return it for later unlinking, if 
		// its a directory or a simlink then ignore it.
		if ( ! is_dir($dir) && ! is_link($dir)) {
			return $dir;
		}
		
		// Right now we know were dealing with a directory, so were able to 
		// loop over each of the files within this dir.
		foreach (scandir($dir) as $item) {
			// If the file stats with a dot then ignore it, this covers ., ..
			// along with any .svn files, hacky, but effective.
			if ($item[0] == '.')
				continue;
			
			// Grab the file, and makesure its a file, if it isn't then we 
			// continue onto the next directory item.
			$file = $this->tidy_up_temp_directory($dir . "/" . $item);
			if ( ! is_file($file))
				continue;
			
			// Remove the file
			$remove = unlink($file);
			
			// Recurse into the new directoy, if it returns false...
			if ( ! $remove) { 
				// Update the permissions for that file and try again.
				chmod($dir . "/" . $item, 0777);
				
				$remove = unlink($file);
				
				// If that fails, then we have a ninja file, and the world 
				// will soon be imploding...
				if ( ! $remove) 
					return false;
			}; 
			
			Profiler::stop(Profiler::start(
				"Controller_Doctrine::tidy_up_temp_directory",
				sprintf("unlinked temp file '%s'.", $file)
				));
		} 
		
		// Remove the directory unless we're ment to keep it.
		if ( ! $keep_this) {
			Profiler::stop(Profiler::start(
				"Controller_Doctrine::tidy_up_temp_directory",
				sprintf("rmdir '%s'.", $dir)
				));
			return rmdir($dir);
		}
		return true;
	}
	
	/**
	 * Used to list all tables that are configured, so we can build them, as 
	 * and when required.
	 * @param void
	 * @return void
	 */
	public function list_tables()
	{
		echo '<pre>';
		foreach (Kohana::config('donk') as $key=> $value) {
			if (in_array($key, array('admin_ip_list'))) {
				continue;
			}
			echo sprintf(
				'Build tables into the %s connection.<br />',
				HTML::anchor(sprintf('doctrine/generate/%s', $key), $key)
				);
		}
		echo '</pre>';
	}
	
	/**
	 * Builds Doctrine
	 * @param void
	 * @return void
	 * @author Iain
	 **/
	public function action_build()
	{
		Donk::instantiate();
		Doctrine_Core::compile('Doctrine.compiled.php', array('mysql'));
	}
}