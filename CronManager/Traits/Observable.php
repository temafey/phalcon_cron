<?php
/**
 * @namespace
 */
namespace CronManager\Traits;

/**
 * Class Observable
 * @package CronManager\Traits
 */
trait Observable 
{	
	static public $MESSAGE_STANDART = 1;
	static public $MESSAGE_ERROR = 2;	
	
	/**
	 * Observer objects
	 * @var array
	 */
	private $__observers = array();
	
	/**
	 * Add new observer
	 * 
	 * @return \CronManager\Traitss\Observable
	 */
	public function addObserver($observer) 
	{
		$this->__observers[] = $observer;
		
		return $this;
	}

	/**
	 * Notify
	 * 
	 * @return \CronManager\Traitss\Observable
	 */
	public function notify($messageType = 1) 
	{
		foreach ($this->__observers as $o) {
			$o->onEvent($this, $messageType);
		}
		
		return $this;
	}
	
	/**
	 * Abstarct get message method
	 *
	 * @return string
	 */
	abstract public function getMessage();
}