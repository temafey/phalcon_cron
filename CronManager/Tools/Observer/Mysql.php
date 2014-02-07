<?php
/**
 * @namespace
 */
namespace CronManager\Tools\Observer;

use CronManager\Traits\Observer;

/**
 * Class Mysql
 * @package CronManager\Tools\Observer
 */
class Mysql
{
	use Observer;

	/**
	 * Log model name
	 * @pvar string
	 */
	private $_model;
	
	/**
	 * Constructor
	 *
	 * @param array $options
	 */
	public function __construct(array $options = array())
	{
		$this->setOptions($options);
	}
	
	/**
	 * Set options
	 *
	 * @param array $options
	 * @return \CronManager\Tools\Observer\Mysql
	 */
	public function setOptions(array $options)
	{	
		if (array_key_exists('model', $options)) {
			$this->_model = $options['logName'];
		} else {
			throw new \Exception("Log model not set");
		}
	
		return $this;
	}
	
	public function onEvent($subject)
	{
		$log = new $this->_model();
		
		switch ($subject->getMessageType()) {
			case 2:
				$log->type = 'error';
				break;
			case 1:
			default:
				$log->type = 'message';
				break;
		}
		
		$log->class = get_class($subject);
		$log->message = $subject->getMessage();
		$log->time = date("Y-m-d H:i:s");
		$log->save();
	}
	
}