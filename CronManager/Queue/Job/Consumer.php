<?php
/**
 * @namespace
 */
namespace CronManager\Queue\Job;

use CronManager\Manager\Executable,
    CronManager\Traits\Daemon\Socket\Client,
    CronManager\Traits\Daemon\Logs,
    CronManager\Queue\Job\Connection\Init;

/**
 * Class Consumer
 * @package CronManager\Queue\Job
 */
class Consumer extends Executable
{
    use Client, Logs, Init {
        Init::_init as _connectionInit;
    }

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
     * @var \Thumper\Consumer
     */
    private $_consumerManager;

    /**
     * Initialize configuration
     *
     * @return void
     */
    protected function _init()
    {
        $this->_connectionInit();
        $this->_config = $this->getDi()->get('config');
        $this->_socketFile = $this->_config->daemon->socket;

        $this->_connection->channel()->queue_declare($this->getManagerQueueName(), false, true, false, false, false, null, null);
        $this->_connection->channel()->queue_purge($this->getManagerQueueName());

        $this->_consumerManager = new \Thumper\Consumer($this->_connection);
        $this->_consumerManager->setExchangeOptions(array('name' => $this->getManagerExchangeName(), 'type' => $this->_config->rabbitmq->exchangeType));
        $this->_consumerManager->setQueueOptions(array('name' => $this->getManagerQueueName()));
        $this->_consumerManager->setCallback($this->_managerHandler());
        $this->_consumerManager->consume(1);

        $this->_consumerJob = new \Thumper\Consumer($this->_connection);
        $this->_consumerJob->setExchangeOptions(array('name' => $this->getJobExchangeName(), 'type' => $this->_config->rabbitmq->exchangeType));
        $this->_consumerJob->setQueueOptions(array('name' => $this->getJobQueueName()));
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
        $this->_message = "Waiting for free pool";
        $this->notify();

        $consumer = new \Thumper\Consumer($this->_connection);
        $consumer->setExchangeOptions(array('name' => $this->getManagerExchangeName(), 'type' => $this->_config->rabbitmq->exchangeType));
        $consumer->setQueueOptions(array('name' => $this->getManagerQueueName()));
        $consumer->setCallback($this->_managerHandler());
        $consumer->consume(1);
        //$this->_consumerManager->consume(1);

        return true;
    }

    /**
     * Wait new job in queue and send command to cron manager
     *
     * @return void
     */
    protected function _consumeJob()
    {
        $this->setMessage("Waiting for new job");
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
            $client->setMessage("Got params for new jobs");
            $client->notify();
            if (!$client->consumeManager()) {
                $client->setMessage("Process terminated");
                $client->notify(2);
                return false;
            }
            $client->setMessage("Send new message to the main procees with new job params");
            $client->notify();
            $client->write($params);

            $client->setMessage("Waiting new job");
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
        $client = $this;
        $callback = function ($params) use ($client)  {
            if ($params == 1) {

            }
            $client->setMessage("Got free pool");
            $client->notify();
        };

        return $callback;
    }
}