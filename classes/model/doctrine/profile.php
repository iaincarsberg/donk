<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Model_Doctrine_Profile
 *
 * @package default
 * @author Iain Carsberg
 **/
class Model_Doctrine_Profile extends Doctrine_Connection_Profiler
{
	/**
	 * Contains pre event ids
	 * @var array
	 **/
	private $events;
	
    public function __call($methodName, $arguments) {
		if (!($arguments[0] instanceof Doctrine_Event)) {
            throw new Doctrine_Connection_Profiler_Exception("Couldn't listen event. Event should be an instance of Doctrine_Event.");
        }

        if (substr($methodName, 0, 3) == 'pre') {
            $arguments[0]->start();
			
			if (strpos($methodName, 'Exec') !== false) {
				$this->events[$arguments[0]->getSequence()] = Profiler::start("Doctrine SQL", $arguments[0]->getQuery());
			}
			
			$this->events[$arguments[0]->getSequence()] = Profiler::start("Doctrine", $methodName);
			
        } else {
			Profiler::stop($this->events[$arguments[0]->getSequence()]);
        }
    }
}