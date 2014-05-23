<?php
/**
 * @namespace
 */
namespace CronManager\Queue\Job;

use CronManager\Manager\Executable,
    CronManager\Traits\Daemon\Socket\Client,
    CronManager\Traits\Daemon\Logs;

/**
 * Class Consumer
 * @package CronManager\Queue\Job
 */
class Consumer extends Executable
{
    use Client,Logs;

    /**
     * Configuration object
     * @var \Phalcon\Config
     */
    private $_config;

    /**
     * @var \Thumper\Consumer
     */
    private $_consumerJob;

    /**
     * Initialize configuration
     *
     * @return void
     */
    protected function _init()
    {
        $this->_config = $this->getDi()->get('config');
        $this->_socketFile = $this->_config->daemon->socket;

        $this->_consumerJob = new \Thumper\Consumer($this->_di->get('thumperConnection')->getConnection());
        $this->_consumerJob->setExchangeOptions(array('name' => $this->_config->rabbitmq->jobExchangeName, 'type' => $this->_config->rabbitmq->exchangeType));
        $this->_consumerJob->setQueueOptions(array('name' => $this->_config->rabbitmq->queueName));
        //$this->_consumerJob->setRoutingKey();
        $this->_consumerJob->setCallback($this->_costumerHandler());
    }

    /**
     * Start consumer process
     *
     * @return void
     */
    public function run()
    {
        $this->_consumeJob();
    }

    /**
     * Wait access to new job in queue by cron manager
     *
     * @return bool
     */
    public function consumeManager()
    {
        if ($this->isTerminated()) {
            unset($this->_consumerJob);
            return false;
        }
        $this->_message = "Wait free pool";
        $this->notify();

        $consumer = new \Thumper\Consumer($this->_di->get('thumperConnection')->getConnection());
        $consumer->setExchangeOptions(array('name' => $this->_config->rabbitmq->managerExchangeName, 'type' => $this->_config->rabbitmq->exchangeType));
        $consumer->setQueueOptions(array('name' => 'cron-cli-queue-manager'));
        $consumer->setCallback($this->_managerHandler());
        $consumer->consume(1);

        return true;
    }

    /**
     * Wait new job in queue and send command to cron manager
     *
     * @return void
     */
    protected function _consumeJob()
    {
        $this->_message = "Wait new job task";
        $this->notify();
        $this->_consumerJob->consume(0);
    }

    /**
     * Handler to send new job to cron manager
     *
     * @return \Closure
     */
    protected function _costumerHandler()
    {
        $client = $this;
        $callback = function ($params) use ($client) {
            if (!$client->consumeManager()) {
                $client->setMessage("Process terminated");
                $client->notify();
                return false;
            }

            $params = igbinary_unserialize($params);
            $client->write(json_encode($params));

            $client->setMessage("Wait new job task");
            $client->notify();
            usleep(200000);

            return true;
        };

        return $callback;
    }

    /**
     * Handler to process access to new job fron cron manager
     *
     * @return \Closure
     */
    protected function _managerHandler()
    {
        $callback = function ($params) {
            return true;
        };

        return $callback;
    }
}