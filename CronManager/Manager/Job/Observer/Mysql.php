<?php
/**
 * @namespace
 */
namespace CronManager\Manager\Job\Observer;

use CronManager\Traits\Observer;

/**
 * Class Mysql
 * @package CronManager\Manager\Job\Observer
 */
class Mysql extends Process
{
	use Observer;
	
	/**
	 * Process log model
	 * @var \Phalcon\Mvc\Model
	 */
	private $_logModel;
	
	/**
	 * Set options
	 *
	 * @param array $options
	 * @return \CronManager\Manager\Job\Observer\File
	 */
	public function setOptions(array $options)
	{
		parent::setOptions($options);
	
		if (!array_key_exists('logModel', $options)) {
			throw new \Exception('Process log model not set!');
		}
	
		$this->_logModel = $options['logModel'];
	
		return $this;
	}
	
	public function onEvent($subject, $messageType)
	{		
		$this->_update();
		$message = $subject->getMessage();
		if ($message === false || $message === null || $message === '') {
			return;
		}
		$log = new $this->_logModel;
		$log->process_id = $this->_processId;
		switch ($messageType) {
			case 2:
				$log->type = 'error';
				break;
			case 1:
			default:
				$log->type = 'message';
				break;
		}
		
		$log->message = $message;
		$log->time = date("Y-m-d H:i:s");
		$log->save();
		if (!$log->save()) {
			print_r($log->getMessages());
		}
	}
	
}