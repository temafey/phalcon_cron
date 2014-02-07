<?php
/**
 * @namespace
 */
namespace CronManager\Manager;

use CronManager\Traits\Observable,
    CronManager\Traits\Message;

/**
 * Class AbstractManager
 * @package CronManager\Manager
 */
abstract class AbstractManager
{	
	use Observable,Message;
	
	/**
	 * Maximum running processes
	 * @var integer
	 */
	protected $_MAX_POOL = 10;
	
	/**
	 * Array of running processes
	 * @var array
	 */
	protected $_pool = array();
	protected $_streams = array();
	protected $_stderr = array();	

	/**
	 * Terminated flag
	 * @var boolean
	 */
	protected $_is_terminated = FALSE;
	
	/**
	 * Stream timeout in seconds
	 * @var integer
	 */
	protected $_streamTimeoutSecond = 1;
	
	/**
	 * Stream timeout in microsecond
	 * @var integer
	 */
	protected $_streamTimeoutMicrosecond = 0;

	/**
	 * Check running jobs
	 *
	 * @return integer
	 */
	protected function checkJobs()
	{
		$running_jobs = 0;
		foreach ($this->_pool as $pid => $job) {
			if (!$job->isRunning()) {
				$this->_message = "Stopping job ".$this->_pool[$pid]->name()." ($pid)" . PHP_EOL;
				$this->notify();
				$this->stopJob($pid);
			} else {
				$running_jobs++;
			}
		}

		return $running_jobs;
	}

	/**
	 * Return free pool index
	 *
	 * @return integer
	 */
	protected function _existsFreePool()
	{
		return (count($this->_pool) < $this->_MAX_POOL) ? true : false;
	}

	/**
	 * Run new job
	 *
	 * @param string $cmd
	 * @param string $name
	 * @param array $observers
	 *
	 * @return boolean|integer
	 */
	public function startJob($cmd, $name = 'job', array $observers = array())
	{
		// broadcast existing jobs
		$this->checkJobs();

		/**$free_pool_slots = $this->_MAX_POOL - count($this->_pool);

		if ($free_pool_slots <= 0) {
			// output error "no free slots in the pool"
			return false;
		}

		if (!$this->_existsFreePool()) {
			return false;
		}*/

		$job = new Job($cmd, $name);
		$job->execute();
		$pid = $job->pid();
		$this->_message = "Starting job $name ($pid) ".date("H:i:s").PHP_EOL;
		$this->notify();
		
		$this->_pool[$pid] = $job;
		$this->_streams[$pid] = $this->_pool[$pid]->getPipe();
		$this->_stderr[$pid] = $this->_pool[$pid]->getStderr();

		foreach ($observers as $params) {
			$observer = $this->_pool[$pid]->observerFactory($params);
			$this->_pool[$pid]->addObserver($observer);
		}
		$job->notify();

		return $pid;
	}

	/**
	 * Destroy(kill) job(daemon)
	 *
	 * @param integer $pid
	 * @return boolean
	 */
	public function stopJob($pid)
	{
		if (!isset($this->_pool[$pid])) {
			return FALSE;
		}
		unset($this->_streams[$pid]);
		unset($this->_stderr[$pid]);
		unset($this->_pool[$pid]);
		
		return true;
	}

	/**
	 * Get job
	 *
	 * @param integer $pid
	 * @return \CronManager\Manager\Job|boolean
	 */
	public function getJob($pid)
	{
		if (!isset($this->_pool[$pid])) {
			return FALSE;
		}
		 
		return $this->_pool[$pid];
	}

	/**
	 * Retrun job name
	 *
	 * @param integer $pid
	 * @return boolean|string
	 */
	public function name($pid)
	{
		if (!isset($this->_pool[$pid])) {
			return FALSE;
		}

		return $this->_pool[$pid]->name();
	}

	public function pipeline($pid, $nohup = FALSE)
	{
		if (!isset($this->_pool[$pid])) {
			return FALSE;
		}

		return $this->_pool[$pid]->pipeline($nohup);
	}

	public function stderr($pid, $nohup = FALSE)
	{
		if (!isset($this->_pool[$pid])) {
			return FALSE;
		}

		return $this->_pool[$pid]->stderr($nohup);
	}

	protected function broadcastMessage($msg)
	{
		// sends selected signal to all child processes
		foreach ($this->_pool as $pid => $job) {
			$job->message($msg);
		}
	}

	protected function broadcastSignal($sig)
	{
		// sends selected signal to all child processes
		foreach ($this->_pool as $pid => $job) {
			$job->signal($sig);
		}
	}
	
	/**
	 * Dispatcher
	 * 
	 * @param array $params
	 * @return void
	 */
	protected function _dispatch(array $params)
	{
		switch ($params['type']) {
			case "exit":
				$this->broadcastSignal(SIGTERM);
				$this->_is_terminated = TRUE;
				break;
			case 'kill':
				$this->stopJob($params['pid']);
				break;
			case "start":
				$jobCmd = implode(" ", $params['args']);
				$observers = isset($params['observers']) ? $params['observers'] : array();
				$this->startJob($jobCmd, $params['name'], $observers);
				break;
			default:
				echo "Dispatch error: ";
				var_dump($params);
				break;
		}
	}

	/**
	 * Show process status by pid
	 *
	 * @param integer $pid
	 * @return array
	 */
	public function show($pid = null)
	{
		if ($this->checkJobs() == 0) {
			$this->_message = "No running jobs\n";
			$this->notify();
		}

		foreach ($this->_pool as $pid => $job) {
			var_dump($job->getStatus2());
		}
	}
	
	/**
	 * Read stdin by all runnning jobs with timoute
	 * 
	 * @param string $write
	 * @param string $except
	 * @return void
	 */
	protected function _stdRead(&$write, &$except)
	{
		$read = $this->_streams;
		$except = $this->_stderr;
	
		if (!(is_array($read) && count($read) > 0)) {
			return;
		}
		if (false === ($num_changed_streams = stream_select($read, $write, $except, $this->_streamTimeoutSecond, $this->_streamTimeoutMicrosecond))) {
			$this->_message = "Some stream error\n";
			$this->notify();
			return;
		}
		if ($num_changed_streams <= 0) {
			return;
		}
		if (!(is_array($read) && count($read) > 0)) {
			return;
		}
		$cmp_array = $this->_streams;
		foreach ($read as $resource) {
			$pid = array_search($resource, $cmp_array, TRUE);
			if ($pid === FALSE) {
				continue;
			}
			// читаем сообщения процессов
			$pool_content = $this->pipeline($pid, TRUE);
			$this->_processMessage($pid, $pool_content, 1);
			$pool_error = $this->stderr($pid, TRUE);
			$this->_processMessage($pid, $pool_error, 2);
			continue;
			
			$job_name = $this->name($pid);

			if ($pool_content) {
				echo $pool_content;
			}

			if ($pool_error) {
				$this->_message = $job_name ." ($pool_index)" . ' [STDERR]: ' . $pool_error.PHP_EOL;
				$this->notify();
			}
		}
	}
	
	/**
	 * Process job message
	 * 
	 * @param integer $pid
	 * @param string $message
	 * @param integer $type
	 * @return void
	 */
	abstract protected function _processMessage($pid, $message, $type);
	
	/**
	 * Run manager
	 *
	 * @return void
	 */
	abstract public function process();
	
	/**
	 * Destructor
	 */
	public function __destruct()
	{
		// destroy pool
		foreach (array_keys($this->_pool) as $pid) {
			$this->stopJob($pid);
		}
	}

}