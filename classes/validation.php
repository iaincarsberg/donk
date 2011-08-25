<?php defined('SYSPATH') or die('No direct script access.');

class Validation extends Kohana_Validation {
	/**
	 * Used to bind aliases to blocks of data
	 * @var array
	 **/
	private $aliases = array();
	
	/**
	 * Used to alias a field of data
	 * @param string $field Contains the field within the validation object.
	 * @param string $alias Contains the alias the $field will be known by
	 * @param boolean $isArray If there is an array of options
	 * @return void
	 */
	public function alias($field, $alias, $is_array=FALSE)
	{
		if ($is_array) {
			if (! array_key_exists($alias, $this->aliases)) {
				$this->aliases[$alias] = array();
			}
			
			$this->aliases[$alias][] = $field;
			
		} else {
			$this->aliases[$alias] = $field;
		}
	}
	
	/**
	 * Used to get aliased data
	 * @param string $alias Contains the alias a block of data is known by
	 * @param boolean $undefined Contains the return value if the item is 
	 *  undefined within the alias list.
	 * @param boolean|string $array_item In some cases you may want to apply
	 * changes to multiple of the same model, to do this you need to parse in
	 * the array_item in the Validator model.
	 * @return string Containing the parsed data.
	 */
	public function get($alias, $undefined=false, $array_item=FALSE)
	{
		// Turn the validation object into an array to make life easier.
		$data = $this->as_array();
		
		if ($array_item) {
			// Makesure the alias exists, and that the alias is used.
			if (! array_key_exists($alias, $this->aliases)) {
				return $undefined;
			}
			
			// Loop over the collection of aliases
			foreach ($this->aliases[$alias] as $item) {
				if (strtolower($item) === strtolower($array_item)) {
					// Makesure the alias exists, and that the alias is used.
					if (! array_key_exists($item, $data)
					) {
						return $undefined;
					}

					// Return the original value.
					return $data[$item];
				}
			}
			return $undefined;
			
		} else {
			// Makesure the alias exists, and that the alias is used.
			if (! array_key_exists($alias, $this->aliases) OR
				! array_key_exists($this->aliases[$alias], $data)
			) {
				return $undefined;
			}
			
			// Return the original value.
			return $data[$this->aliases[$alias]];
		}
	}
}
