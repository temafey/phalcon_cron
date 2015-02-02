<?php
/**
 * @namespace
 */
namespace CronManager\Queue\Job;

use CronManager\Manager\Executable,
	Cron\CronExpression,
	CronManager\Queue\Model\Job,
	CronManager\Cron\SecondsField,
	DateTime,
	DateTimeZone,
	CronManager\Traits\Daemon\Logs,
    CronManager\Queue\Job\Connection\Init,
    CronManager\Queue\Job\Producer\Add;

/**
 * Class Producer
 * @package CronManager\Queue\Job
 */
class Producer extends Executable 
{	
	use  Init, Add, Logs {
        Init::_init as _connectionInit;
        Add::_init as _addInit;
    }
	
	/**
	 * Actual next minute jobs
	 * @var array
	 */
	protected $_jobs = [];
	
	/**
	 * Crontabs objects
	 * @var array
	 */
	protected $_crontabs = [];
	
	/**
	 * Cron seconds validator
	 * @var \CronManager\Cron\SecondsField;
	 */
	protected $_seconds;

    /**
     * Initialize configuration
     *
     * @return void
     */
    protected function _init()
    {
        $this->_connectionInit();
        $this->_addInit();
    }

	/**
	 * Start producer process
	 * 
	 * @return void
	 */
	public function run() 
	{
		$jobs = [];
		$tmpJobs = [];
		$lastJobsTimeStart = [];
		$first = true;
		$last = false;
        $next = false;
		$timezone = new DateTimeZone(date_default_timezone_get());
		while (!$this->isTerminated()) {
			$currentDate = new DateTime(null, $timezone);
			$sec = $currentDate->format("s");
			if (!$first && $last === $sec) {
				usleep(500000);
				continue;
			}
			if ($first || ($sec != 0 && ($sec % 50) == 0)) {
				$next = true;
				$tmpJobs = $this->_getJobs(clone $currentDate);
			}
			if ($first) {
				$jobs = $tmpJobs;
				$first = false;
			} elseif ($next && $sec >= 0) {
				$jobs = $tmpJobs;
				$next = false;
			}
			foreach ($jobs as $job) {
				if (!$this->_cronSecondDue($currentDate, $job['second'])) {
					continue;
				}
				$timestamp = $currentDate->getTimestamp();
				if (array_key_exists($job['id'], $lastJobsTimeStart) && $lastJobsTimeStart[$job['id']] === $timestamp) {
					continue;
				}
				$lastJobsTimeStart[$job['id']] = $currentDate->getTimestamp();
				$this->_runJob($job);
			}
			$last = $sec;
		}
	}
	
	/**
	 * Load and set all cron jobs
	 *
	 * @param array $jobs
	 * @return \CronManager\Queue\Job\Producer
	 */
	public function setJobs()
	{
		$this->clearJobs();
		$cronjobs = Job::find(["status = '1'"])->toArray();
		if (!is_array($cronjobs)) {
			return $this;
		}
		$this->_jobs = $cronjobs;
		foreach ($this->_jobs as $job) {
			$this->_crontabs[$job['id']] = CronExpression::factory($job['minute']." ".$job['hour']." ".$job['day']." ".$job['month']." ".$job['week_day']);
		}
		$this->_seconds = new SecondsField();
	
		return $this;
	}
	
	/**
	 * Clear cron jobs
	 *
	 * @return \CronManager\Queue\Job\Producer
	 */
	public function clearJobs()
	{
		$this->_jobs = [];
		$this->_crontabs = [];
		$this->_seconds	= null;
	
		return $this;
	}
	
	/**
	 * Return if exists jobs that will be run in next minute
	 *
	 * @return array
	 */
	protected function _getJobs(DateTime $datetime)
	{
		$this->setJobs();
		$jobs = [];
		foreach ($this->_jobs as $job) {
			$tmpDatetime = clone $datetime;
			if (!$this->_crontabs[$job['id']]->isDue($tmpDatetime->modify("+1 minute"))) {
				continue;
			}
			$jobs[] = $job;
		}
	
		return $jobs;
	}
	
	/**
	 * Parse crontime by second
	 *
	 * @param DateTime $timestamp
	 * @param string $cronsecond
	 * @return boolean
	 */
	protected function _cronSecondDue(DateTime $dateTime, $part)
	{
		if (strpos($part, ',') === false) {
			return $this->_seconds->isSatisfiedBy($dateTime, $part);
		} else {
			foreach (array_map('trim', explode(',', $part)) as $listPart) {
				if ($this->_seconds->isSatisfiedBy($dateTime, $listPart)) {
					return true;
				}
			}
		}
		 
		return false;
	}
	
	/**
	 * Add job task to queue
	 *
	 * @param array $job
	 * @return \CronManager\Queue\Job\Producer
	 */
	protected function _runJob(array $job)
	{
		$task = [];
		$task['type'] = 'start';
		$task['name'] = $job['name'];
		$hash = md5($job['id']."_".$job['name']."_".microtime());
		//$task['cmd'] = $job['command']." ".$job['id'];
		$task['args'] = [$job['command'], $job['id'], $hash];
		$task['observers'] = [['observer' => 'mysql', 'hash' => $hash, 'job_id' => $job['id'], 'options' => ['processModel' => '\CronManager\Queue\Model\Process', 'logModel' => '\CronManager\Queue\Model\Log']]];
		
		$this->_addQueue($task);
	
		return $this;
	}
}

