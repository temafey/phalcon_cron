<?php
/**
 * @namespace
 */
namespace CronManager\Manager\Job\Observer;

/**
 * Class Process
 * @package CronManager\Manager\Job\Observer
 */
abstract class Process 
{
	/**
	 * Process id
	 * @var integer
	 */
	protected $_processId;

	/**
	 * Process hash
	 * @var string
	 */
	protected $_hash;
	
	/**
	 * Process command
	 * @var string
	 */
	protected $_cmd;
	
	/**
	 * Pid 
	 * @var integer
	 */
	protected $_pid;
	
	/**
	 * Job id
	 * @var integer
	 */
	protected $_jobId;
	
	/**
	 * Process action name
	 * @var string
	 */
	protected $_action;
	
	/**
	 * Destruct process update status
	 * @var string
	 */
	protected $_destructStatus = 'finished';
	
	/**
	 * Parent process hash
	 * @var string
	 */
	protected $_parentHash = 1;
	
	/**
	 * Process model
	 * @var \Phalcon\Mvc\Model
	 */
	protected $_processModel;
		
	public function __construct($hash, $cmd, $pid, $jobId, $action, array $options = array())
	{
		$this->_hash = $hash;
		$this->_cmd = $cmd;
		$this->_pid = $pid;
		$this->_jobId = $jobId;
		$this->_action = $action;
		$this->setOptions($options);
		$this->_init();
	}
	
	/**
	 * Set options
	 *
	 * @param array $options
	 * @return \CronManager\Manager\Job\Observer\Mysql
	 */
	public function setOptions(array $options)
	{
		if (isset($options['destructStatus'])) {
			$this->_destructStatus = $options['destructStatus'];
		}
		if (isset($options['parentHash'])) {
			$this->_parentHash = $options['parentHash'];
		}
		if (!isset($options['processModel'])) {
			throw new \Exception('Process model not set!');
		}

		$this->_processModel = $options['processModel'];
		
		return $this;
	}
	
	protected function _init()
	{
		if (!($process = call_user_func(array($this->_processModel, 'findFirst'), "hash = '".$this->_hash."'"))) {
			$process = new $this->_processModel;
			$attempt = 1;
		} else  {
			$attempt = $process->attempt + 1;
		}
		
		$process->hash = $this->_hash;
		$process->command = $this->_cmd;
		$process->job_id = $this->_jobId;		
		$process->pid = $this->_pid;
		$process->action = $this->_action;
		$process->stime = date("Y-m-d H:i:s");
		$process->time = 0;
		$process->status = 'running';
		$process->phash = $this->_parentHash;
		$process->attempt = $attempt;
		if ($process->save()) {
			$this->_processId = $process->id;
		} else {
			print_r($process->getMessages());
		}
	}
	
	public function __destruct()
	{
		$process = call_user_func(array($this->_processModel, 'findFirst'), "id = '".$this->_processId."'");
		if (!$process) {
			return false;
		}
		if ($process->status == 'completed') {
			return false;
		}
		$process->status = $this->_destructStatus;
		$process->time = time() - strtotime($process->stime);
		$process->update();
	}
	
	protected function _update()
	{	
		$process = call_user_func(array($this->_processModel, 'findFirst'), "id = '".$this->_processId."'");
		if (!$process) {
			return false;
		}
		$process->time = time() - strtotime($process->stime);
		$process->update();
	}
	
}