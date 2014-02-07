<?php
/**
 * @namespace
 */
namespace CronManager\Traits\Daemon;

/**
 * Class Logs
 * @package CronManager\Traits\Daemon
 */
trait Logs
{
	/**
	 * Log file fullname
	 * @var string
	 */
	private $_logFile = '/tmp/php.daemon.log';
	
	/**
	 * Error log fullname
	 * @var string
	 */
	private $_errorLogFile = '/tmp/php.daemon.error.log';
	
	private $_STDIN;
	private $_STDOUT;
	private $_STDERR;
	
	/**
	 * Initialize logs
	 *
	 * @return void
	 */
	private function _initLogs()
	{
		fclose(STDIN);
		fclose(STDOUT);
		fclose(STDERR);
		$this->_STDIN = fopen('/dev/null', 'r');
		$this->_STDOUT = fopen($this->_logFile, 'ab');
		$this->_STDERR = fopen($this->_errorLogFile, 'ab');
	}
	
	/**
	 * Close logs
	 *
	 * @return void
	 */
	private function _closeLogs()
	{
		if ($this->_STDIN) {
			fclose($this->_STDIN);
		}
		if ($this->_STDIN) {
			fclose($this->_STDIN);
		}
		if ($this->_STDIN) {
			fclose($this->_STDIN);
		}
	}
	
}