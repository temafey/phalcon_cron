<?php
/**
 * @namespace
 */
namespace CronManager\Traits;

/**
 * Class Locking
 * @package CronManager\Traits
 */
trait Locking 
{
	private $_lockFile = '/tmp/php.lock';
	private $_lockFH = false;
	private $_notUnlock = false;
	
	/**
	 * Looking process
	 * 
	 * @return void
	 */
	protected function _locking()
	{
		$pid = false;
		if (is_file($this->_lockFile) && filesize($this->_lockFile) > 0) {
			$pid = trim(file_get_contents($this->_lockFile));
		}
		if (FALSE !== ($this->_lockFH = fopen($this->_lockFile, 'w'))) {
			if (TRUE !== flock($this->_lockFH, LOCK_EX | LOCK_NB)) {
				if (!($pid && !$this->_checkPid(trim($pid)))) {
					$this->_notUnlock = true;
					fwrite($this->_lockFH, $pid);
					exit;
				}
			}
		} else {
			exit;
		}

		fwrite($this->_lockFH, getmypid());
	}
	
	/**
	 * Ulocking process
	 * 
	 * @return void
	 */
	protected function _unlocking()
	{
		if ($this->_lockFH && !$this->_notUnlock) {
			fclose($this->_lockFH);
			unlink($this->_lockFile);
		}
	}
	
	/**
	 * Check process id
	 * 
	 * @return boolean
	 */
	protected function _checkPid($pid)
	{
		exec("ps -p $pid -o pid", $status);
		
		return (count($status) > 1 && $status[1] > 0) ? true : false;
	}
}