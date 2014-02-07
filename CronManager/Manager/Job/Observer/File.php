<?php
/**
 * @namespace
 */
namespace CronManager\Manager\Job\Observer;

use CronManager\Traits\Observer;

/**
 * Class File
 * @package CronManager\Manager\Job\Observer
 */
class File extends Process
{
	use Observer;
	
	/**
	 * Log file path
	 * @var string
	 */
	private $_path;
	
	/**
	 * Log file resource
	 * @var 
	 */
	private $_logFile;
		
	public function __construct($hash, $cmd, $pid, $jobId, $action, array $options = array())
	{
		parent::__construct($hash, $cmd, $pid, $jobId, $action, $options);
		
		$this->_openLogFile();
	}
	
	/**
	 * Set options
	 *
	 * @param array $options
	 * @return \CronManager\Manager\Job\Observer\File
	 */
	public function setOptions(array $options)
	{
		parent::setOptions($options);
		
		if (array_key_exists('logPath', $options)) {
			$this->_path = rtrim($options['logPath'], "/\\")."/";
		} else {
			$this->_path = sys_get_temp_dir()."/";
		}
		
		return $this;
	}
	
	/**
	 * Init log file
	 * 
	 * @return void
	 */
	private function _openLogFile()
	{
		$this->_logFile = fopen($this->_path.$this->_action.$this->_jobId.".log", "a+");
	}
	
	public function onEvent($subject, $messageType)
	{
		$this->_update();
		$message = $subject->getMessage();
		if ($message === false || $message === null || $message === '') {
			return;
		}
		$str = "------------------------------------".PHP_EOL;
		$str .= "Process id: ".$this->_processId.", date: ".date("Y-m-d H:i:s")."\n";
		switch ($messageType) {
			case 2:
				$str .=  " Message type: error".PHP_EOL;
				break;
			case 1:
			default:
				$str .=  " Message type: info".PHP_EOL;
				break;
		}
		
		$str .= $message.PHP_EOL;
		$str .= PHP_EOL;

		fwrite($this->_logFile, $str);
	}
	
	public function __destruct()
	{
		parent::__destruct();		
		fclose($this->_logFile);
	}
}