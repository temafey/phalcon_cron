<?php
/**
 * @namespace
 */
namespace CronManager\Queue\Job\Producer;

/**
 * Class Add
 * @package CronManager\Queue\Job\Producer
 */
trait Add  
{	
	/**
	 * Queue producer
	 * @var \Thumper\Producer
	 */
	protected $_queueProducer;
	
	/**
	 * Initialize queue producer
	 * 
	 * @return void
	 */
	protected function _init()
	{
		$this->_connect();

        parent::_init();
	}
	
	protected function _connect()
	{
		$config = $this->getDi()->get('config');
		$this->_queueProducer = new \Thumper\Producer($this->_connection);
		$this->_queueProducer->setExchangeOptions(['name' => $this->getJobExchangeName(), 'type' => $config->rabbitmq->exchangeType]);
	}
	
	/**
	 * Add job to queue
	 *
	 * @param  array $job
	 * @return \CronManager\Queue\Job\Producer\Add
	 */
	protected function _addQueue(array $task)
	{
		try {
			$this->_queueProducer->publish(serialize($task));
		} catch (\Exception $e) {
			$this->_message = $e->getMessage();
			$this->notify(2);
			
			$this->_connect();
			$this->_queueProducer->publish(serialize($task));
		}
		$this->_message = "Add new job `".$task['name']."` in queue";
		$this->notify();
		
		return $this;
	}
}

