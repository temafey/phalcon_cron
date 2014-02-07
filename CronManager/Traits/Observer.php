<?php
/**
 * @namespace
 */
namespace CronManager\Traits;

/**
 * Class Observer
 * @package CronManager\Traits
 */
trait Observer 
{	
	/**
	 * Abstarct onevent method
	 * 
	 * @param object $subject
	 * @return void
	 */
	abstract public function onEvent($subject, $messageType);

	/**
	 * Add observe to observable
	 * 
	 * @param Observable $observable
	 * @return \CronManager\Traitss\Observer
	 */
	public function observe(Observable $observable) 
	{
		$observable->addObserver($this);
		
		return $this;
	}
}