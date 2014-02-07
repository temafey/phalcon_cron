<?php
/**
 * @namespace
 */
namespace CronManager\Manager\Job\Observer;

use CronManager\Traits\Observer;

/**
 * Class Stdout
 * @package CronManager\Manager\Job\Observer
 */
class Stdout extends Process
{
	use Observer;
	
	public function onEvent($subject, $messageType)
	{
		$str = "------------------------------------".PHP_EOL;
		$str .= "Process id: ".$this->_processId.", date: ".date("Y-m-d H:i:s").PHP_EOL;
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
		$str .= PHP_EOL;

		echo $str;
		
		$this->_update();
	}
}