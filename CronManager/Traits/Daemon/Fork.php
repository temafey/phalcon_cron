<?php
/**
 * @namespace
 */
namespace CronManager\Traits\Daemon;

/**
 * Class Fork
 * @package CronManager\Traits\Daemon
 */
trait Fork
{
	/**
	 * Process pid number
	 * @var integer
	 */
	private $_pid;
	
	/**
	 * Pid file fullname
	 * @var string
	 */
	private $_pidFile = '/var/run/php.daemon.pid';
	
	/**
	 * Forks the currently running process
	 * 
	 * @return void 
	 */
	private function _forkInit()
	{
		umask(0);
		$pid = pcntl_fork();
		if ($pid > 0) {
			//echo "Daemon process started\n";
			exit;
		}
		$sid = posix_setsid();
		if ($sid < 0) {
			exit(2);
		}
	}
	
	/**
	 * Initialize pid file
	 *
	 * @return void
	 */
	private function _initPIDFile()
	{
		$this->_pid = getmypid();
		file_put_contents($this->_pidFile, $this->_pid);
	}
		
	/**
	 * Unlink pid file
	 *
	 * @return void
	 */
	private function _closePIDFile()
	{
		if (file_exists($this->_pidFile)) {
			unlink($this->_pidFile);
		}
	}
	
	/**
	 * Return pid number
	 *
	 * @return integer
	 */
	public function getPID()
	{
		return $this->_pid;
	}

    /**
     * Return pid file full path
     *
     * @return string
     */
    public function getPIDFile()
    {
        return $this->_pidFile;
    }
}