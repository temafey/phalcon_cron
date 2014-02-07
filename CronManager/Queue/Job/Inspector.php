<?php
/**
 * @namespace
 */
namespace CronManager\Queue\Job\Queue;

use CronManager\Manager\Executable,
	CronManager\Queue\Model\Process,
	CronManager\Queue\Model\Job,
	CronManager\Traits\Daemon\Socket\Client,
	CronManager\Traits\Daemon\Logs;

/**
 * Class Inspector
 * @package CronManager\Queue\Job\Queue
 */
class Inspector extends Executable 
{	
	use Producer\Add,Client,Logs;

    /**
     * Configuration object
     * @var \Phalcon\Config
     */
    private $_config;
	
	/**
	 * Start inspector process
	 * 
	 * @return void
	 */
	public function run() 
	{
		$this->_config = $this->getDi()->get('config');
		$this->_socketFile = $this->_config->daemon->socket;
		$this->_stopRunningProcesses();
		while (!$this->isTerminated()) {
			$this->_stopProcesses();
			$this->_runProcesses();
			$this->_ttlProcesses();
			$this->_rerunProcesses();
			usleep(500000);
		}
	}
	
	/**
	 * Stop running processes
	 *
	 * @return void
	 */
	protected function _stopRunningProcesses()
	{
		if (!($processes = $this->_getProcesses('running')) || $processes->count() == 0) {
			return;
		}
	
		foreach ($processes as $process) {
			$this->_stopProcess($process);
		}
	}
	
	
	/**
	 * Stop processes with status stop
	 * 
	 * @return void
	 */
	protected function _stopProcesses()
	{
		if (!($processes = $this->_getProcesses('stop')) || $processes->count() == 0) {
			return;
		}
		
		foreach ($processes as $process) {
			$this->_stopProcess($process);
		}
	}
	
	/**
	 * Stop processes by ttl
	 *
	 * @return void
	 */
	protected function _ttlProcesses()
	{
		if (!($processes = $this->_getProcesses('running')) || $processes->count() == 0) {
			return;
		}
	
		foreach ($processes as $process) {
			$ttl = Job::findFirst("id = '".$process->job_id."'")->ttl;
			if ($ttl > 0 && ($ttl < (time() - strtotime($process->stime)))) {
				$this->_stopProcess($process, 'aborted');
			}
		}
	}
	
	/**
	 * Run processes with status run
	 *
	 * @return void
	 */
	protected function _runProcesses()
	{
		if(!($processes = $this->_getProcesses('run')) || $processes->count() == 0) {
			return;
		}
		foreach ($processes as $process) {
			$this->_runProcess($process);
		}
	}
	
	/**
	 * Run processes with status aborted
	 *
	 * @return void
	 */
	protected function _rerunProcesses()
	{
		if(!($processes = $this->_getProcesses('aborted')) || $processes->count() == 0) {
			return;
		}
		foreach ($processes as $process) {
			$this->_runProcess($process);
		}
	}
	
	/**
	 * Stop process by pid
	 * 
	 * @param \CronManager\Queue\Model\Process $pid
	 * @param string $status
	 * @return \CronManager\Queue\Job\Queue\Inspector
	 */
	protected function _stopProcess(Process $process, $status = 'stopped')
	{
		$task = [];
		$task['type'] = 'kill';
		$task['pid'] = $process->pid;
		 
		//$this->_addQueue($task);
		$this->_message = "Stop process with id: ".$process->id.", pid: ".$process->pid.", name: ".$process->action;
		$this->notify();
		$this->write(json_encode($task));
		$this->_changeStatus($process->id, $status);

		return $this;
	}
	
	/**
	 * Add process to queue for run  
	 *
	 * @param \CronManager\Queue\Model\Process $process
	 * @return \CronManager\Queue\Job\Queue\Inspector
	 */
	protected function _runProcess(Process $process)
	{
		$task = [];
		$job = Job::findFirst("id = '".$process->job_id."'");
		if ($process->phash == 1) {
			$cmd = explode(" ", $process->command);
		} else {
			//$cmd = Process::findFirst("hash = '".$process->phash."'")->command;
			//$cmd = trim(str_replace($process->job_id." ".$process->phash, "", $process->command));
			$cmd = explode(" ", $process->command);
		}
		if ($job->max_attempts == $process->attempt) {
			$this->_changeStatus($process->id, 'error');
			return false;
		}
		$task['type'] = 'start';
		$task['name'] = $process->action;
		$task['args'] = $cmd;
		$task['observers'] = [
			[
				'observer' => 'mysql', 
				'hash' => $process->hash, 
				'job_id' => $process->job_id,
				'options' => [
					'processModel' => '\CronManager\Queue\Model\Process',
					'logModel' => '\CronManager\Queue\Model\Log',
					'parentHash' => $process->phash
				]
			]
		];
		$this->_message = "Add new job `".$task['name']."` in queue";
		$this->notify();
		$this->_addQueue($task);
		$this->_changeStatus($process->id, 'waiting');
		
		return $this;
	}
	
	/**
	 * Return processes by status
	 * 
	 * @param string $status
	 * @return array
	 */
	protected function _getProcesses($status)
	{
		$processes = Process::find("status = '".$status."'");
		
		return $processes;
	}
	
	/**
	 * Change process status
	 * 
	 * @param integer $id
	 * @param string $status
	 * @return void 
	 */
	protected function _changeStatus($id, $status)
	{
		$process = Process::findFirst("id = '".$id."'");
		$process->status = $status;
		$res = $process->update();
	}
}

