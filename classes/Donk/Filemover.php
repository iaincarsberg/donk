<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Donk_Filemover
 *
 * @package Donk
 * @author Iain Carsberg
 **/
class Donk_Filemover
{
	/**
	 * Used to execute a model move.
	 * @param string $dir Contains the root directory
	 * @param array $modules Contains all active modules
	 * @return void
	 */
	public static function execute($dir, $modules)
	{
		$files = new self();
		$files->set_src_directory($dir);
		
		// Add module-less files to the process list.
		$modules[''] = APPPATH;
		
		// Build a list of all concrete files within the $dir and their 
		// eventual destinations.
		foreach ($modules as $module=> $path) {
			$files->discover_paths_for_concrete_files(
				$module, 
				$files->prep_target($path),
				array_keys($modules)
				);
		}
		
		// Append all of the abstract files, and their eventual destinations.
		$files->discover_paths_for_abstract_files();
		
		// Move all of the files.
		return $files->move_files();
	}
	
	/**
	 * Contains the path to the generated files.
	 * @var string
	 **/
	protected $src_dir;
	
	/**
	 * Contains a list of files, and where to copy them.
	 * @var array
	 **/
	public $copy_file_into = array();
	
	/**
	 * Used to set the src directory
	 * @param string $dir Contains the src directory.
	 * @return this To allow object chaining.
	 */
	public function set_src_directory($dir)
	{
		$this->src_dir = $dir;
		return $this;
	}
	
	/**
	 * Commend
	 * @param void|type $param Comment
	 * @return void|Type Comment
	 */
	protected function class_model_path()
	{
		return implode(array(
			'classes',
			'model'
		), DIRECTORY_SEPARATOR);
	}
	
	/**
	 * Used to append the classes/model sub-directory to a target path
	 * @param string $target Contains the root module files will be copied to
	 * @return string Containing the prepared string.
	 */
	public function prep_target($target)
	{
		return $target . $this->class_model_path();
	}
	
	/**
	 * Used to convert an absolute path into a module-less path
	 * @param string $target Contains a target with class
	 * @return string Containing a stripped target
	 */
	protected function strip_module($target)
	{
		$token = $this->class_model_path();
		return str_replace(
			'.php',
			'',
			substr($target, strpos($target, $token) + strlen($token) + 1)
			);
	}
	
	/**
	 * Used to discover files.
	 * @param string $module Contains a module we're looking for files in.
	 * @return void
	 */
	public function discover_paths_for_concrete_files($module, $target, $root_modules = array())
	{
		$scan_dir = $this->src_dir;
		$is_root = TRUE;
		
		// If the module is empty, it means we're scanning a root level module
		if (strlen($module) > 0) {
			$is_root = FALSE;
			$scan_dir .= DIRECTORY_SEPARATOR . $module;
		}
		
		// Some modules maynot have generated files.
		if (! is_dir($scan_dir)) {
			return;
		}
		
		// Scan the contents of the scan dir for files or nested directories.
		foreach (scandir($scan_dir) as $file) {
			// Build the skip list
			$skip_list = array('.', '..', 'generated');
			
			// If this is a root directory we want to skip all other modules
			if ($is_root) {
				$skip_list = array_merge($skip_list, $root_modules);
			}
			
			// If the file is in the skip_list then we skip past it.
			if (in_array(strtolower($file), $skip_list)) {
				continue;
			}
			
			$current_file = $scan_dir . DIRECTORY_SEPARATOR . $file;
			$current_target = $target . DIRECTORY_SEPARATOR . $file;
			
			if (is_dir($current_file)) {
				$this->discover_paths_for_concrete_files(
					str_replace($this->src_dir . DIRECTORY_SEPARATOR, '', $current_file),
					$current_target
					);
			}
			
			if (is_file($current_file) AND
				substr($current_file, -4) === '.php'
			) {
				$stripped = $this->strip_module($current_target);
				
				// Convert the string into its class name, to see if it should
				// end up in the donk module.
				$class_name = sprintf('Model_%s', str_replace(
					DIRECTORY_SEPARATOR,
					'_',
					$stripped
				));
				
				// Check to see if the class_name is meant to be fored into 
				// the donk module.
				foreach (Kohana::modules() as $name=> $module) {
					if (in_array($class_name, (array)Kohana::$config->load($name . '_local_models'))) {
						$current_target = implode(array(
							$module,
							$this->class_model_path(),
							DIRECTORY_SEPARATOR,
							$stripped,
							'.php'
						), '');
					}
				}
				
				$this->copy_file_into[$current_file] = array(
					'file'=> $current_target,
					'replace'=> FALSE
				);
			}
		}
	}
	
	/**
	 * Used to find all of the generated files.
	 * @param void
	 * @return void
	 */
	public function discover_paths_for_abstract_files($sub_directory = FALSE)
	{
		$scan_dir = $this->src_dir . DIRECTORY_SEPARATOR . 'generated';
		
		// Append the sub_directory to the scan_dir
		if ($sub_directory) {
			$scan_dir .= DIRECTORY_SEPARATOR . $sub_directory;
		}
		
		// If there are no generated files do nothing.
		if (! is_dir($scan_dir)) {
			return;
		}
		
		// Scan the contents of the scan dir for files or nested directories.
		foreach (scandir($scan_dir) as $file) {
			if (in_array($file, array('.', '..'))) {
				continue;
			}
			
			$current_file = $scan_dir . DIRECTORY_SEPARATOR . $file;
			
			if (is_dir($current_file)) {
				$this->discover_paths_for_abstract_files(
					str_replace($this->src_dir . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR, '', $current_file)
					);
			}
			
			if (is_file($current_file) AND
				substr($current_file, -4) === '.php'
			) {
				$this->copy_file_into[$current_file] = array(
					'file'=> implode(array(
						APPPATH,
						$this->class_model_path(),
						substr(
							$current_file, 
							strpos($current_file, DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR)
						), '')),
					'replace'=> TRUE
				);
			}
		}
	}
	
	/**
	 * Used to move all the files contained within the copy_file_into array.
	 * @param void
	 * @return void
	 */
	public function move_files()
	{
		$moved = array();
		foreach ($this->copy_file_into as $src => $target) {
			// Makesure something doesn't go amis.
			if (! file_exists($src)) {
				throw new Exception(sprintf("The source file '%s' doesn't exist, something odd has happened.", $src));
			}
			
			if ($target['replace'] OR 
				! file_exists($target['file'])
			) {
				$moved[] = $target['file'];
				
				// Make any required directories.
				$this->mkdir($target['file']);

				// Create the new file.
				file_put_contents($target['file'], file_get_contents($src));
			}
		}
		return $moved;
	}
	
	/**
	 * Used to make a required directory.
	 * @param string $path Contains the path to the required directory
	 * @return void
	 */
	protected function mkdir($path)
	{
		$directory = '';
		$path = explode(DIRECTORY_SEPARATOR, $path);
		$filename = array_pop($path);
		
		foreach ($path as $pos=> $dir) {
			if ($pos === 0) {
				$directory .= $dir;
				continue;
			}
			
			// Append the directory.
			$directory .=  DIRECTORY_SEPARATOR . $dir;
			
			// If the directory doesn't exist, create it.
			if (! is_dir($directory)) {
				mkdir($directory);
			}
		}
	}
}
