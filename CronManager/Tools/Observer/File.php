<?php
/**
 * @namespace
 */
namespace CronManager\Tools\Observer;

use CronManager\Traits\Observer;

/**
 * Class File
 * @package CronManager\Tools\Observer
 */
class File
{
	use Observer;
	
	/**
	 * Log file path
	 * @var string
	 */
	private $_path;
	
	/**
	 * Log file name
	 * @var string
	 */
	private $_name;
	
	/**
	 * Log file resource
	 * @var 
	 */
	private $_logFile;
	
	/**
	 * Constructor
	 * 
	 * @param array $options
	 */
	public function __construct(array $options = array())
	{
		$this->setOptions($options);
		
		$this->_openLogFile();
	}
	
	/**
	 * Set options
	 *
	 * @param array $options
	 * @return \CronManager\Tools\Observer\File
	 */
	public function setOptions(array $options)
	{
		if (isset($options['logPath'])) {
			$this->_path = rtrim($options['logPath'], "/\\")."/";
		} else {
			$this->_path = sys_get_temp_dir()."/";
		}
		
		if (isset($options['logName'])) {
			$this->_name = $options['logName'];
		} else {
			$this->_name = false;
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
		if ($this->_name) {
			$this->_logFile = fopen($this->_path.$this->_name.".log", "a+");
		}
	}
	
	public function onEvent($subject)
	{
		if (!$this->_logFile) {
			$this->_logFile = fopen($this->_path.strtolower(get_class($subject)).".log", "a+");
		}
		$str = "------------------------------------\n";
		$str .= "Notify date: ".date("Y-m-d H:i:s")."\n";
		switch ($subject->getMessageType()) {
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

		fwrite($this->_logFile, $str);
		
		$this->_update();
	}
	
	public function __destruct()
	{
		if ($this->_logFile) {	
			fclose($this->_logFile);
		}
	}
}