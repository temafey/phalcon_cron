<?php
/**
 * @namespace
 */
namespace CronManager\Manager;

use CronManager\Traits\DIaware,
    CronManager\Manager\Job\Observer\Mysql,
    CronManager\Manager\Job\Observer;

/**
 * Class QueueManager
 * @package CronManager\Manager
 */
class QueueManager
{
	use DIaware;
	
	CONST POOL_MAX = 20;
	protected $_pool = array();
	protected $_streams = array();
	protected $_stderr = array();

	protected $_is_terminated = FALSE;
	
	/**
	 * Queue Consumer
	 * @var \Thumper\Consumer
	 */
	private $_consumer;
	
	public function __construct($dependencyInjector)
	{
		$this->setDi($dependencyInjector);
		$this->_init();
	}
	
	protected function _init()
	{
		$config = $this->getDi()->get('config');
		$this->_consumer = new \Thumper\Consumer($this->getDi()->get('thumperConnection')->getConnection());
		$this->_consumer->setExchangeOptions(['name' => $config->rabbitmq->exchangeName, 'type' => $config->rabbitmq->exchangeType]);
		$this->_consumer->setQueueOptions(['name' => $config->rabbitmq->queueName]);
		//$this->_consumer->setRoutingKey();
		$this->_consumer->setCallback($this->_callback());
	}
	
	protected function _callback()
	{
		$manager = $this;
		$callback = function ($params) use ($manager) {
			$params = igbinary_unserialize($params);
			$manager->dispatchMessage($params);
		};
	
		return $callback;
	}

	public function __destruct()
	{
		// destroy pool
		foreach (array_keys($this->_pool) as $pid) {
			$this->stopJob($pid);
		}
	}

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
				echo "Stopping job ".$this->_pool[$pid]->name()." ($pid)" . PHP_EOL;
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
	protected function existsFreePool()
	{
		return (count($this->_pool) < self::POOL_MAX) ? true : false;
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

		$free_pool_slots = self::POOL_MAX - count($this->_pool);

		if ($free_pool_slots <= 0) {
			// output error "no free slots in the pool"
			return false;
		}

		if (!$this->existsFreePool()) {
			return false;
		}

		$job = new Job($cmd, $name);
		$job->execute();
		$pid = $job->pid();
		echo "Starting job $name ($pid) ". date("H:i:s") . PHP_EOL;

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
	 * 
	 * 
	 * @param array $params
	 * @return void
	 */
	public function dispatchMessage(array $params)
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
				$this->dispatch($params);
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
			echo "No running jobs\n";
		}

		foreach ($this->_pool as $pid => $job) {
			var_dump($job->getStatus2());
		}
	}

	/**
	 * Run manager
	 *
	 * @return void
	 */
	public function process()
	{
		stream_set_blocking(STDIN, 0);

		$write = NULL;
		$except = NULL;

		while (!$this->_is_terminated) {
			$this->_queueRead();
			$this->_stdRead($write, $except);
			$this->checkJobs();
			//usleep(200000);
		}
	}
	
	protected function _queueRead()
	{
		if (!$this->existsFreePool()) {
			return;
		}
		$this->_consumer->consume(1);
	}
	
	protected function _stdRead($write, $except)
	{
		/*
		 из-за особенности функции stream_select приходится особым образом работать с массивами дескрипторов
		*/
		$read = $this->_streams;
		$except = $this->_stderr;
		$read[0] = STDIN;
		
		if (!(is_array($read) && count($read) > 0)) {
			return;
		}
		if (false === ($num_changed_streams = stream_select($read, $write, $except, 0, 300000))) {
			echo "Some stream error\n";
			return;
		} 
		if ($num_changed_streams <= 0) {
			return;
		}
		// есть что почитать
		if (!(is_array($read) && count($read) > 0)) {
			return;
		}
		$cmp_array = $this->_streams;
		$cmp_array[0] = STDIN;
		foreach ($read as $resource) {
			$pid = array_search($resource, $cmp_array, TRUE);
			if ($pid === FALSE) {
				continue;
			}
			if ($pid == 0) {
				// stdin
				$content = '';
				while ($cmd = fgets(STDIN)) {
					if (!$cmd) {
						break;
					}
					$content .= $cmd;
				}
				$content = trim($content);
				if ($content) {
					// если Process Manager словил на вход какую-то строчку - парсим и решаем что делать
					$params = [];
					$parts = explode(" ", $content);
					$params['type'] = isset($parts[0]) ? array_shift($parts) : false;
					$params['name'] = isset($parts[1]) ? array_shift($parts) : false;
					$params['args'] = $parts;

					$this->dispatchMain($params, $pid);
				}
				//echo "stdin> " . $cmd;
			} else {
				// читаем сообщения процессов
				$pool_content = $this->pipeline($pid, TRUE);
				$job_name = $this->name($pid);

				if ($pool_content) {
					echo $pool_content;
				}

				$pool_content = $this->stderr($pid, TRUE);
				if ($pool_content) {
					echo $job_name ." ($pool_index)" . ' [STDERR]: ' . $pool_content."\n";
				}
			}
		}
		
	}

}