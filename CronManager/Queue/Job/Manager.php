<?php
/**
 * @namespace
 */
namespace CronManager\Queue\Job;

use CronManager\Manager\AbstractManager,
	CronManager\Traits\DIaware,
	CronManager\Traits\Daemon\Fork,
	CronManager\Traits\Daemon\Event,
	CronManager\Traits\Daemon\Logs,
	CronManager\Traits\Daemon\Socket\Server,
	CronManager\Traits\Locking,
    CronManager\Queue\Job\Connection\Init,
    CronManager\Tools\Stuff\PidChecker;

/**
 * Class Manager
 * @package CronManager\Queue\Job
 */
class Manager extends AbstractManager 
{	
	use DIaware, Fork, Event, Logs, Server, Locking, Init {
        Init::_init as _connectionInit;
    }
	
	/**
	 * Config
	 * @var \Phalcon\Config
	 */
	protected $_config;
	
	protected $_MAX_POOL = 10;
	protected $_MIN_FREE_MEMORY_MB = 1000;
	protected $_MIN_FREE_MEMORY_PERCENTAGE = 10;
	protected $_MAX_CPU_LOAD = 40;
	protected $_ACTION_STATUS = 1;
	
	CONST STATUS_RUNNING = 1;
	CONST STATUS_PENDING = 2;
	CONST STATUS_RESTART = 3;
	CONST STATUS_STOP	 = 4;
	
	/**
	 * Queue Producer
	 * @var \Thumper\Producer
	 */
	private $_producer;
	
	/**
	 * Count of adding queue processses
	 * @var integer
	 */
	private $_queueCount = 0;
	
	/**
	 * @var boolean
	 */
	private $_queueFlag = true;
	
	/**
	 * @var boolean
	 */
	private $_accessJob = false;
	
	/**
	 * Init config flag, after first initialize config change flag to false 
	 * @var boolean
	 */
	private $_configFlag = true;
	
	/**
	 * Main processes
	 * @var array
	 */
	private $_mainProcesses = array();

    /**
     * Queue error message
     * @var string
     */
    protected $_queuePublishErrorMessage = '';

    /**
     * File name for running cron manager tasks
     * @var string
     */
    protected $_mainRunTaskFile = 'cron.php';

    /**
     * If true not execute destruct logic
     * @var bool
     */
    protected $_noDestructing = false;

	/**
	 * Constructor
	 * 
	 * @param \Phalcon\DI $dependencyInjector
	 */
	public function __construct($dependencyInjector)
	{
		ignore_user_abort(false);
		//register_shutdown_function([$this, '__destruct']);
		pcntl_signal(SIGTERM, [$this, '__destruct']);
		$this->setDi($dependencyInjector);
        $this->_init();
	}

    /**
     * Initialize
     *
     * @return void
     */
    protected function _init()
    {
        $this->_connectionInit();
    }

	
	/**
	 * Destrcuctor
	 */
	public function __destruct()
	{
        if ($this->_noDestructing) {
            return false;
        }
		parent::__destruct();
		$this->_daemonClose();
	}
	
	/**
	 * Initialize cron manager queue
	 * 
	 * @return void
	 */
	protected function _initQueue()
	{
		$this->_producer = new \Thumper\Producer($this->_connection);
		$this->_producer->setExchangeOptions(['name' => $this->getManagerExchangeName(), 'type' => $this->_config->rabbitmq->exchangeType]);
	}
	
	/**
	 * Initialize cron manager configuration from database
	 * 
	 * @return void
	 */
	protected function _initConfig()
	{		
		$settings = $this->_config->daemon->settings;
		$type = $settings['type'];
		switch ($type) {
			case 'model':
				$environment = $settings['environment'];
				$model = $settings['model'];
				try {
					$params = call_user_func([$model, 'findFirst'], "status='1' AND environment='".$environment."'");
				} catch (\Exception $e) {
					$this->_message = $e->getMessage().PHP_EOL;
					$this->notify(static::$MESSAGE_ERROR);
					$params = false;
				}
				if ($params) {
					if ($params->action_status == self::STATUS_RESTART && $this->_configFlag) {
						$params->action_status = self::STATUS_RUNNING;
						$params->update();
					}
					$params = $params->toArray();
				}
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
		if (isset($params['min_free_memory_mb'])) {
			$this->_MIN_FREE_MEMORY_MB = (int) $params['min_free_memory_mb'];
		}
		if (isset($params['min_free_memory_percentage'])) {
			$this->_MIN_FREE_MEMORY_PERCENTAGE = (int) $params['min_free_memory_percentage'];
		}
		if (isset($params['max_cpu_load'])) {
			$this->_MAX_CPU_LOAD = (int) $params['max_cpu_load'];
		}
		if (isset($params['max_cpu_load'])) {
			$this->_MAX_CPU_LOAD = (int) $params['max_cpu_load'];
		}
		if (isset($params['action_status'])) {
			$this->_ACTION_STATUS = (int) $params['action_status'];
		}
		if ($this->_configFlag) {
			$this->_configFlag = false;
		}
	}
	
	/**
	 * Initialize cron manager loging observer
	 * 
	 * @return void
	 */
	protected function _initObserver()
	{
		$this->addObserver(new \CronManager\Tools\Observer\Stdout());
	}

	/**
	 * Notify about all running processes
	 * 
	 * @return void
	 */
	protected function _eventStatistic()
	{
		$this->_message = "Active socket connections: ".count($this->_socketConnections).PHP_EOL;
		$this->_message .= "Active jobs: ".count($this->_pool).PHP_EOL;
		$this->_message .= "Free system memory: ".$this->_getFreeMemory().PHP_EOL;
		$this->_initConfig();
		
		$allocateMemory = 0;
		foreach ($this->_pool as $pid => $job) {
			if (!$job->isRunning()) {
				$this->_message .= "Stopping job `".$this->_pool[$pid]->name()."`` ($pid)".PHP_EOL;
				$this->stopJob($pid);
			} else {
				$job->setMessage(false);
				$job->notify();
				$status = $job->getStatus2();
				$memory = intval($status['memory']);
				$allocateMemory += $memory;
				if ($memory > 500000) {
					$this->_message .= "Alert process ".$job->name()." with pid ".$pid." allocate ".$memory." memory!";
				} else {
					//echo "Process ".$job->name()." with pid ".$pid." allocate ".$memory." memory!\n";
				}
			}
		}
		$this->_message .= "Allocate system memory: ".$allocateMemory.PHP_EOL;
		if ($this->_queuePublishErrorMessage) {
			$this->_message .= $this->_queuePublishErrorMessage.PHP_EOL;
		}
		$this->_message .= PHP_EOL;
		$this->notify();
	}

	/**
	 * Run manager process
	 *
	 * @return boolean
	 */
	public function process()
	{
		$this->_streamTimeoutSecond = 0;
		$this->_streamTimeoutMicrosecond = 300000;

		$this->_config = $this->getDi()->get('config');
		
		$this->_daemonInit();
		$this->_initConfig();
		if ($this->_ACTION_STATUS == self::STATUS_STOP) {
			$this->_message = "Exit from cron manager, because action in 'STOP' status!";
			$this->notify();
			return false;
		}

        $write = NULL;
        $except = NULL;

        $this->_initQueue();
		$this->_runMainProcesses();
        sleep(1);
        $this->_producer->publish(1);
        sleep(1);
		while (!$this->_is_terminated) {
			$this->_accessJob = $this->_queuePublish();
			$this->_eventLoop();
			$this->_stdRead($write, $except);
			$this->checkJobs();
			$this->_checkMainProcesses();
			$this->_checkActionStatus();
			usleep(200000);
		}
		$this->_message = "Exit from cron manager";
		$this->notify();
		
		return true;
	}
	
	/**
	 * Run all main processes
	 * 
	 * @return void
	 */
	protected function _runMainProcesses()
	{
		if ($this->_ACTION_STATUS == self::STATUS_PENDING) {
			$this->_message = "Cron pending";
			$this->notify();
			return;
		}
		$this->_runMainProcess('producer');
		$this->_runMainProcess('consumer');
		$this->_runMainProcess('inspector');
	}
	
	/**
	 * Run main process by his special key
	 * 
	 * @param string $process
	 * @return void
	 */
	protected function _runMainProcess($process)
	{
		$documentRoot = $this->_config->application->documentRoot;
		switch ($process) {
			case 'producer':
				$this->_mainProcesses['producer'] = $this->_runProducer($documentRoot);
				break;
			case 'consumer':
				$this->_mainProcesses['consumer'] = $this->_runConsumer($documentRoot);
				break;
			case 'inspector':
				$this->_mainProcesses['inspector'] = $this->_runInspector($documentRoot);
				break;
		}
	}
	
	/**
	 * Run producer main process
	 *
	 * @return integer
	 */
	protected function _runProducer($documentRoot)
	{
		$this->_message = "Run main process `producer`";
		$this->notify();
		$observers = [];
		/*$observers = [
			[
				'observer' => 'mysql',
				'hash' => 'producer',
				'job_id' => 0,
				'options' => [
					'processModel' => '\CronManager\Queue\Model\Process',
					'logModel' => '\CronManager\Queue\Model\Log'
				]
			]
		];*/
		
		return $this->startJob($documentRoot."/".$this->_mainRunTaskFile." cron-producer init", 'cron_producer', $observers);
	}
	
	/**
	 * Run consumer main process
	 *
	 * @return integer
	 */
	protected function _runConsumer($documentRoot)
	{
		$this->_message = "Run main process `consumer`";
		$this->notify();
		$observers = [];
		
		return $this->startJob($documentRoot."/".$this->_mainRunTaskFile." cron-consumer init", 'cron_consumer', $observers);
	}
	
	/**
	 * Run inspector main process
	 * 
	 * @return integer
	 */
	protected function _runInspector($documentRoot)
	{
		$this->_message = "Run main process `inspector`";
		$this->notify();
		$observers = [];
		
		return $this->startJob($documentRoot."/".$this->_mainRunTaskFile." cron-inspector init", 'cron_inspector', $observers);
	}
	
	/**
	 * Checking main processes and run it if it down
	 * 
	 * @return void
	 */
	protected function _checkMainProcesses()
	{
		if ($this->_ACTION_STATUS == self::STATUS_PENDING) {
			$this->_message = "Cron pending";
			$this->notify();
			return;
		}
		foreach ($this->_mainProcesses as $process => $pid) {
			if ($this->getJob($pid)) {
				continue;
			}
			$this->_message = "Main process '".$process."' was down";
			$this->notify();
			$this->_runMainProcess($process);
		}
	}
	
	/**
	 * Chech action status
	 * 
	 * @return void
	 */
	protected function _checkActionStatus()
	{
		if ($this->_ACTION_STATUS == self::STATUS_STOP || $this->_ACTION_STATUS == self::STATUS_RESTART || $this->_ACTION_STATUS == self::STATUS_PENDING) {
			$this->_stopAllJobs();
			if ($this->_ACTION_STATUS == self::STATUS_STOP || $this->_ACTION_STATUS == self::STATUS_RESTART) {
				$this->_is_terminated = true;
			}
		}
	}
	
	/**
	 * Stop all cron procceses and terminate manager
	 * 
	 * @return void
	 */
	protected function _stopAllJobs()
	{
		$this->_message = "Stop all cron jobs";
		$this->notify();
		
		$this->_stopMainProcesses();
		
		foreach ($this->_pool as $pid => $job) {
			//$job->signal(SIGINT);
			$this->_message = "Stoping process `".$job->getName()."`";
			$this->notify();
			$this->stopJob($pid);
		}
	}
	
	/**
	 * Stop all main processes
	 * 
	 * @return void
	 */
	protected function _stopMainProcesses()
	{
		$this->_stopMainProcess('producer');
		$this->_stopMainProcess('consumer');
		$this->_stopMainProcess('inspector');
	}
	
	/**
	 * Stop main process by his special key
	 * 
	 * @param string $process
	 * @return void
	 */
	protected function _stopMainProcess($process)
	{
		switch ($process) {
			case 'producer':
				$this->_stopProducer();
				break;
			case 'consumer':
				$this->_stopConsumer();
				break;
			case 'inspector':
				$this->_stopInspector();
				break;
		}
	}
	
	/**
	 * Stop producer main process
	 *
	 * @return void
	 */
	protected function _stopProducer()
	{
		if (!$this->getJob($this->_mainProcesses['producer'])) {
			return; 
		}
		$this->_message = "Stop main process `producer`";
		$this->notify();
		
		$pid = $this->_mainProcesses['producer'];
		$this->_pool[$pid]->signal(SIGINT);
		$this->stopJob($pid);
	}
	
	/**
	 * Run consumer main process
	 *
	 * @return void
	 */
	protected function _stopConsumer()
	{
		if (!$this->getJob($this->_mainProcesses['consumer'])) {
			return;
		}
		$this->_message = "Stop main process `consumer`";
		$this->notify();
		
		$pid = $this->_mainProcesses['consumer'];
		$this->_pool[$pid]->signal(SIGINT);
		
		if (!$this->_accessJob) {
			$this->_producer->publish(1);
			sleep(1);
		}
		$producer = new \Thumper\Producer($this->getDi()->get('thumperConnection')->getConnection());
		$producer->setExchangeOptions(['name' => $this->_config->rabbitmq->jobExchangeName, 'type' => $this->_config->rabbitmq->exchangeType]);
		$producer->publish(serialize([]));
		
		$this->stopJob($pid);
	}
	
	/**
	 * Stop inspector main process
	 * 
	 * @return void
	 */
	protected function _stopInspector()
	{
		if (!$this->getJob($this->_mainProcesses['inspector'])) {
			return;
		}
		$this->_message = "Stop main process `inspector`";
		$this->notify();
		
		$pid = $this->_mainProcesses['inspector'];
		$this->_pool[$pid]->signal(SIGINT);
		$this->stopJob($pid);
	}
	
	
	/**
	 * Process new message
	 * 
	 * @return void
	 */
	protected function _processMessage($pid, $message, $type)
	{
		if (empty($message)) {
			return;
		}
		$name = $this->name($pid);		
		switch ($name) {
			case 'cron_consumer':
			case 'cron_producer':
			case 'cron_inspector':
				$this->setMessage(strtoupper(str_replace("_", " ", $name))." PID: ".$pid.", message: ".$message);
				$this->notify();
				break;
		}
	}
	
	/**
	 * Start new process job
	 * 
	 * @return integer|boolean
	 */
	public function startJob($cmd, $name = 'job', array $observers = array())
	{
		if (!($pid = parent::startJob($cmd, $name , $observers))) {
			$this->setMessage("Job `".$name."` not started");
			$this->notify();
			return false;
		}
		$this->_queueFlag = true;
		
		return $pid;
	}
	
	/**
	 * Stop process job by his pid
	 * 
	 * @return boolean
	 */
	public function stopJob($pid)
	{
		if (!parent::stopJob($pid)) {
			return false;
		}
		--$this->_queueCount;
		
		return true;
	}
	
	/**
	 * Initialize daemon
	 *
	 * @return void
	 */
	protected function _daemonInit()
	{	
		$this->_eventTimerInterval = 5000000;
		$this->_pidFile = $this->_config->daemon->pid;
		$this->_logFile = $this->_config->daemon->log;
		$this->_errorLogFile = $this->_config->daemon->error;
		$this->_socketFile = $this->_config->daemon->socket;
		$this->_lockFile = $this->_config->daemon->lock;

        $this->_noDestructing = true;

        if (PidChecker::checkIsDaemonRunning($this->getPIDFile())) {
            echo "Process running!".PHP_EOL;
            exit(2);
        } else {
            $this->deleteSocketFile();
        }

		$this->_forkInit();
        $this->_noDestructing = false;

        $this->_locking();
		$this->_initPIDFile();
	    $this->_initLogs();
	    $this->_initObserver();
        $this->_initSocket();
		$this->_initEventBase();
		$this->_initTimer();
		
		$this->_DESCRIPTORS = ['read_persist' => EV_READ | EV_PERSIST];
		foreach ($this->_DESCRIPTORS as $name => $descriptor) {
			$handler = $this->_getEventHandler($name);
			$this->_setEvent($name, $this->_socket, $descriptor, $handler);
		}
	}
	
	/**
	 * Close daemon
	 *
	 * @return void
	 */
	protected function _daemonClose()
	{
		$this->_closeEvents();
		$this->_closeBuffers();
		$this->_closeSocketConnections();
		$this->_closeSocket();
		$this->_closeLogs();
		$this->_closePIDFile();
		$this->_unlocking();
	}
	
	/**
	 * Publish new message in queue with new job access
	 * 
	 * @return boolean
	 */
	protected function _queuePublish()
	{
		if (!$this->_queueFlag) {
			$this->_queuePublishErrorMessage = "Queue flag false status".PHP_EOL;
			return false;
		}
		
		if ($this->_MAX_CPU_LOAD !== 0) {
			$load = sys_getloadavg();
			if ($load[0] > $this->_MAX_CPU_LOAD) {
				$this->_queuePublishErrorMessage = "Cpu load ".$load.PHP_EOL;
				return false;
			}
		}
		if ($this->_MIN_FREE_MEMORY_PERCENTAGE !== 0) {
			$memory = $this->_getFreeMemory(true);
			if ($memory < $this->_MIN_FREE_MEMORY_PERCENTAGE) {
				$this->_queuePublishErrorMessage = "Free memory ".$memory."%".PHP_EOL;
				return false;
			}
		}
		if ($this->_MIN_FREE_MEMORY_MB !== 0) {
			$memory = $this->_getFreeMemory(false);
			if ($memory < $this->_MIN_FREE_MEMORY_MB) {
				$this->_queuePublishErrorMessage = "Free memory ".$memory." Mb".PHP_EOL;
				return false;
			}
		}
		if (!$this->_existsFreePool() || ($this->_MAX_POOL - $this->_queueCount) == 0) {
			$this->_queuePublishErrorMessage = "Max pool ".$this->_queueCount.PHP_EOL;
			return false;
		}
		
		$this->setMessage("Publish access to add new job");
		$this->notify();
		
		$this->_queuePublishErrorMessage = false;
		$this->_producer->publish(1);
		++$this->_queueCount;
		$this->_queueFlag = false;
		
		return true;
	}
	
	/**
	 * Return free system memory
	 * 
	 * @param boolean $percentage
	 * @return integer
	 */
	protected function _getFreeMemory($percentage = false)
	{
		if ($percentage) {
			exec("free | grep Mem | awk '{print $4/$2 * 100.0}'", $memory);
		} else {
			exec("free | grep Mem | awk '{print $4}'", $memory);
		}
		
		return $memory[0];
	}

    /**
     * @param $name
     * @return array
     */
    protected function _getEventHandler($name)
	{
		switch ($name) {
			case 'read_persist':
				return [$this, 'onAccept'];
				break;
		}
	}
	
	/**
     * Init new socket connection
     * 
     * @return true
	 */
	public function onAccept($socket, $flag)
	{
		$id = $this->_setSocketConnection();
		$connection = $this->_getSocketConnection($id);
		
		$onRead = $this->_getBufferReadHandler();
		$onWrite = $this->_getBufferWriteHandler();
		$onError = $this->_getBufferErrorHandler();
		
		$this->_setEventBuffer($id, $connection, $onRead, $onWrite, $onError, EV_READ | EV_PERSIST);
		
		return true;
	}
	
	/**
	 * Close socket connection after timeout or error
	 * 
	 * @return void
	 */
	public function onError($buffer, $error, $id) 
	{		
		$this->_closeEventBuffer($id, EV_READ | EV_WRITE);
		$this->_closeSocketConnection($id);
	}
	
	/**
 	 * Read from socket connection
 	 * 
 	 * @return void
	 */
	public function onRead($buffer, $id) 
	{
		$content = $this->_readEventBuffer($id, 256);
        $this->setMessage('Read new message from socket');
        $this->notify();
		if (\Engine\Tools\String::isSerialized($content)) {
            $params = unserialize($content);
			$this->_dispatch($params);
		} elseif (\Engine\Tools\String::isJson($content)) {
            $params = json_decode($content);
            if (!is_array($params)) {
                if (is_object($params)) {
                    $params = (array) $params;
                } else {
                    $this->_message = "Params for dispatch by cron manager incorrect!";
                    $this->notify();
                    var_dump($content, $params);
                }
            }
            $this->_dispatch($params);
        } else {
			var_dump($content);
		}
	}

	/**
	 * Return handler
	 *
	 * @return \Closure
	 */
	protected function _getBufferReadHandler()
	{
		$server = $this;
		$handler = function ($buffer, $id) use ($server) {
			return $server->onRead($buffer, $id);
		};
	
		return [$this, 'onRead'];
	}
	
	/**
	 * Return handler
	 *
	 * @return \Closure
	 */
	protected function _getBufferWriteHandler()
	{
		return null;
	}
	
	/**
	 * Return handler
	 *
	 * @return \Closure
	 */
	protected function _getBufferErrorHandler()
	{
		$server = $this;
		$handler = function ($buffer, $error, $id) use ($server) {
			return $server->onError($buffer, $error, $id);
		};
	
		return array($this, 'onError');
	}
}

