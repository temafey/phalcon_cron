<?php
/**
 * @namespace
 */
namespace CronManager\Tools\Observer;

use CronManager\Traits\Observer;

/**
 * Class Stdout
 * @package CronManager\Tools\Observer
 */
class Stdout
{
	use Observer;
	
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
	 * @return \CronManager\Tools\Observer\Stdout
	 */
	public function setOptions(array $options)
	{	
		return $this;
	}
	
	public function onEvent($subject, $messageType)
	{
		$str = PHP_EOL."Class: ".get_class($subject).", date: ".date("Y-m-d H:i:s").PHP_EOL;
		switch ($messageType) {
			case 2:
				$str .=  "Message type: error".PHP_EOL;
				break;
			case 1:
			default:
				$str .=  "Message type: info".PHP_EOL;
				break;
		}
		
		$str .= $subject->getMessage().PHP_EOL;

		echo $str;
	}
}