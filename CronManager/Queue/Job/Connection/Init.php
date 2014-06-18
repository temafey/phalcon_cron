<?php
/**
 * @namespace
 */
namespace CronManager\Queue\Job\Connection;

/**
 * Trait Init
 * @package CronManager\Queue\Job\Consumer
 */
trait Init
{	
	/**
	 * Queue producer
	 * @var \Thumper\Producer
	 */
	protected $_connection;

    /**
     * Queue/Excahnge name prefix
     * @var string
     */
    protected $_prefix;
	
	/**
	 * Initialize queue producer
	 * 
	 * @return void
	 */
	protected function _init()
	{
        $config = $this->getDi()->get('config');
		$this->_connection = $this->getDi()->get('thumperConnection')->getConnection();
        $this->_prefix = $config->rabbitmq->queuePrefix;
	}

    /**
     * Return queue adapter connection
     *
     * @return \Thumper\Producer
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * Return exchange name for jobs
     *
     * @return string
     */
    public function getJobExchangeName()
    {
        return $this->_prefix."-cli-job-exchange";
    }

    /**
     * Return exchange name for manage jobs
     *
     * @return string
     */
    public function getManagerExchangeName()
    {
        return $this->_prefix."-cli-manager-exchange";
    }

    /**
     * Return queue name for jobs
     *
     * @return string
     */
    public function getJobQueueName()
    {
        return $this->_prefix."-cli-job-queue";
    }

    /**
     * Return queue name for manage jobs
     *
     * @return string
     */
    public function getManagerQueueName()
    {
        return $this->_prefix."-cli-manager-queue";
    }
}

