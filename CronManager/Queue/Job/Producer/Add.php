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
	}
	
	protected function _connect()
	{
		$config = $this->getDi()->get('config');
		$this->_queueProducer = new \Thumper\Producer($this->getDi()->get('thumperConnection')->getConnection());
		$this->_queueProducer->setExchangeOptions(['name' => $config->rabbitmq->jobExchangeName, 'type' => $config->rabbitmq->exchangeType]);
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
			$this->_queueProducer->publish(igbinary_serialize($task));
		} catch (\Exception $e) {
			$this->_message = $e->getMessage();
			$this->notify();
			
			$this->_connect();
			$this->_queueProducer->publish(igbinary_serialize($task));
		}
		$this->_message = "Add new job `".$task['name']."` in queue";
		$this->notify();
		
		return $this;
	}
}

