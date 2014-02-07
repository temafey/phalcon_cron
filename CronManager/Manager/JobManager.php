<?php
/**
 * @namespace
 */
namespace CronManager\Manager;

use CronManager\Traits\DIaware,
    CronManager\Manager\Job\Observer\Mysql,
    CronManager\Manager\Job\Observer;

/**
 * Class JobManager
 * @package CronManager\Manager
 */
class JobManager
{
	/**
	 * Config 
	 * @var \Phalcon\Config
	 */
	protected $_config;
	
	protected $_MAX_POOL = 10;
	protected $_MAX_MEMORY_MB = 1000;
	protected $_MAX_MEMORY_PERCENTAGE = 10;
	protected $_MAX_CPU_LOAD = 40;
	
	protected $_pool = array();
	protected $_streams = array();
	protected $_stderr = array();

	protected $_is_terminated = FALSE;
	protected $_dispatch_function = NULL;

	/**
	 * Queue Consumer
	 * @var \Thumper\Producer
	 */
	private $_producer;
	
	public function __construct($dependencyInjector)
	{
		$this->setDi($dependencyInjector);
		$this->_init();
	}
	
	protected function _init()
	{
		$this->_config = $this->getDi()->get('config');
		$this->_initConfig();
		
		$this->_producer = new \Thumper\Producer($this->getDi()->get('thumperConnection')->getConnection());
		$this->_producer->setExchangeOptions(['name' => $this->_config->rabbitmq->managerExchangeName, 'type' => $this->_config->rabbitmq->exchangeType]);
	}
	
	protected function _initConfig()
	{		
		$settings = $this->_config->daemon->settings;
		$type = $settings['type'];
		switch ($type) {
			case 'model':
				$environment = $settings['environment'];
				$model = $settings['model'];
				$params = call_user_func(array($model, 'findOne'), "status='1' AND environment => '".$environment."'");
				break;
			case 'array':
			default:
				$params = $settings['params'];
				break;
		}
		if (!$params || !is_array($params)) {
			return;
		}
		if (isset($params['max_pool'])) {
			$this->_MAX_POOL = (int) $params['max_pool'];
		}
		if (isset($params['max_memory_mb'])) {
			$this->_MAX_MEMORY_MB = (int) $params['max_memory_mb'];
		}
		if (isset($params['max_memory_percentage'])) {
			$this->_MAX_MEMORY_PERCENTAGE = (int) $params['max_memory_percentage'];
		}
		if (isset($params['max_cpu_load'])) {
			$this->_MAX_CPU_LOAD = (int) $params['max_cpu_load'];
		}
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
	protected function getFreeIndex()
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

		if (!$this->getFreeIndex()) {
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

	// если была зарегистрирована пользовательская функция разбора - используем ее
	protected function dispatch($params)
	{
		if (is_callable($this->_dispatch_function)) {
			call_user_func($this->_dispatch_function, $params);
		} else {
			if (is_array($params)) {
				print_r($params);
			} else {
				echo $params;
			}
		}
	}

	// регистрация пользовательской функции для разбора
	public function registerDispatch($callable)
	{
		if (is_callable($callable)) {
			$this->_dispatch_function = $callable;
		} else {
			trigger_error("$callable is not callable func", E_USER_WARNING);
		}
	}

	// разбираем пользовательский ввод
	protected function dispatchMain(array $params, $pid)
	{
		$val = (array_key_exists('name', $params)) ?  $params['name'] : false;
		switch ($params['type']) {
			case "exit":
				$this->broadcastSignal(SIGTERM);
				$this->_is_terminated = TRUE;
				break;
			case "test":
				echo 'sending test' . PHP_EOL;
				$this->broadcastMessage('test');
				$this->broadcastSignal(SIGUSR1);
				break;
			case 'kill':
				if (array_key_exists('pid', $params)) {
					$kPid = (int) $params['pid'];
				} else {
					$kPid = ($val !== '' && (int) $val >= 0) ? (int) $val : -1;
				}
				if ($kPid >= 0) {
					$this->stopJob($kPid);
				}
				break;
			case "start":
				$jobCmd = implode(" ", $params['args']);
				$observers = array_key_exists('observers', $params) ? $params['observers'] : array();
				$this->startJob($jobCmd, $val, $observers);
				break;
			case "show":
				$this->show($val);
				break;
			case "check":
				$result = $this->getFreeIndex();
				if ($pid > 0) {
					$this->_pool[$pid]->message(json_encode(['sender' => 'manager', 'type' => 'freePool', 'result' => $result]));
					$this->_pool[$pid]->signal(SIGUSR1);
				}
				break;
			default:
				$this->dispatch($params);
				break;
		}

		return FALSE;
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
			$this->_queuePublish();
			$this->_stdRead($write, $except);
			$this->checkJobs();
			usleep(200000);
		}
	}
	
	protected function _queuePublish()
	{
		if (!$this->existsFreePool()) {
			return;
		}
		$this->_producer->publish(1);
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

				if (($params = json_decode($pool_content, true))) {
                    $this->dispatchMain($params, $pid);
                } else {
                	var_dump($pool_content);
                }

				$pool_content = $this->stderr($pid, TRUE);
				if ($pool_content) {
					echo $job_name ." ($pool_index)" . ' [STDERR]: ' . $pool_content."\n";
				}
			}
		}
		
	}

}