<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Model base class. All models should extend this class.
 *
 * @package    Kohana
 * @category   Models
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
abstract class Kohana_Model extends Doctrine_Record {

	/**
	 * Create a new model instance. A [Database] instance or configuration
	 * group name can be passed to the model. If no database is defined, the
	 * "default" database group will be used.
	 *
	 *     $model = Model::factory($name);
	 *
	 * @param   string   model name
	 * @param   mixed    Database instance object or string
	 * @return  Model
	 */
	public static function factory($name, $db = NULL)
	{
		// Add the model prefix
		$class = 'Model_'.$name;

		return new $class($db);
	}
	
	/**
	 * Used to validate an object based on data held within the base class
	 * @param string|Kohana_Model $model Contains either a model, or a string
	 * @param Validation $validator Contains an validator we're added rules to
	 * @param array $fields Contains a list of which variables in this model 
	 * to mixin validation for.
	 * @return void
	 */
	public static function mixin_validation($model, $validator, $fields)
	{
		if (! ($model instanceof Kohana_Model)) {
			$model = self::factory($model);
		}
		if (is_string($fields)) {
			$fields = array($fields);
		}
		
		$processed_fields = array();
		foreach ($fields as $field => $alias) {
			// Allow support for a single list of fields that directly match
			// the field names, used in situations where the field name is 
			// expected in the posted data.
			if (is_int($field)) {
				$field = $alias;
			}
			
			$processed_fields[$field] = $alias;
		}
		
		foreach ($processed_fields as $field=> $alias) {
			if ($model->getTable()->hasColumn($field)) {
				$definition = $model->getTable()->getColumnDefinition($field);
				
				if (is_array($alias)) {
					foreach ($alias as $item) {
						self::mixin_validate_add_rule($validator, $definition, $item, $model, $field, TRUE);
					}
					
				} else {
					self::mixin_validate_add_rule($validator, $definition, $alias, $model, $field);
				}
				
				// Sometimes we want to have our models mixin custom validation
				if (method_exists($model, 'mixin_rules')) {
					// The idea with $processed_fields is that you do 
					// something like in your model:
					//
					// public function mixin_rules($validator, $fields)
					// {
					//	$validator->rule($fields['contact_id'], 'Model_Contact::contact_exists');
					// }
					//
					// Using the variable as the key in the fields array.
					$model->mixin_rules($validator, $processed_fields);
				}
				
			} else {
				throw new Exception(sprintf(
					'DONK - Kohana_Model: The model "%s" doesn\'t contain the "%s" field.',
					get_class($model),
					$field
					));
			}
		}
	}
	
	/**
	 * Used to add a rule to the validator
	 * @param type $validator Comment
	 * @param type $definition Comment
	 * @param type $alias Comment
	 * @param type $model Comment
	 * @param type $field Comment
	 * @param type $is_array Comment
	 * @return void
	 */
	public static function mixin_validate_add_rule($validator, $definition, $alias, $model, $field, $is_array=FALSE)
	{
		$validator->alias($alias, sprintf('%s::%s', get_class($model), $field), $is_array);
		
		// If the field mustn't be null then tell the validator.
		if (array_key_exists('notnull', $definition)) {
			$validator->rule($alias, 'not_empty');
		}
		
		// If the field is an enumeral
		if (array_key_exists('values', $definition)) {
			// enum
			// getEnumValues($model_column);
		}
		
		switch ($definition['type']) {
			case 'string':
				$validator->rule($alias, 'max_length', array(':value', 
					$definition['length']
					));
				break;
			
			case 'decimal':
				$validator->rule($alias, 'decimal', array(':value', 
					sprintf('{0,%d}', $definition['scale']), 
					sprintf('{0,%d}', $definition['length'])
					));
				break;
		}
	}
	
	/**
	 * Used to apply data validated by the Model=>mixin_validation function.
	 * @param Validator $validator Contains a Validator with parsed and alised
	 * records which are to be applied to the model directly.
	 * @param boolean|string $array_item In some cases you may want to apply
	 * changes to multiple of the same model, to do this you need to parse in
	 * the array_item in the Validator model.
	 * @return $this To allow object chaining.
	 */
	public function mixin_apply($validator, $array_item=FALSE)
	{
		// Loop over the columns in the collection.
		foreach (array_keys($this->getTable()->getColumns()) as $field) {
			// Get the validated inputs, we use the classname and field pair
			// as this prevents unvalidated data creeping into this automated 
			// system, in theory...
			$value = $validator->get(
				sprintf('%s::%s', get_class($this), $field),
				'_!_undefined_!_',
				$array_item
				);
			
			// Makesure the value is valid, and set the records field.
			if ($value !== '_!_undefined_!_') {
				$this->$field = $value;
			}
		}
		
		return $this;
	}
	
	/**
	 * Used to save a unique item to the database
	 * @param array $unique_items Contains a list of values that mustn't yield
	 * a return in-order for the save to happen.
	 * @return void
	 */
	public function save_unique($unique_items)
	{
		// Select this model.
		$query = Doctrine_Query::create()
			->from(sprintf('%s i', get_class($this)));
		
		// Build the query
		foreach ($unique_items as $item) {
			$this->$item = trim($this->$item);
			$query->andWhere(sprintf('i.%s = ?', $item), trim($this->$item));
		}
		
		// If there is no prior instance that looks like the one we just 
		// created then save it, otherwise do nothing.
		if (($unique = $query->fetchOne()) === FALSE) {
			$this->save();
			return $this;
			
		} else {
			return $unique;
		}
	}
} // End Model
