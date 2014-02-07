<?php
/**
 * @namespace
 */
namespace CronManager\Queue\Model;

/**
 * Class Settings
 * @package CronManager\Queue\Model
 */
class Settings extends \Phalcon\Mvc\Model 
{

    /**
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $id;

    /**
     * @Column(type="string", length=200, nullable=false)
     */
    public $environment;

    /**
     * @Column(type="integer", length=11, nullable=false)
     */
    public $max_pool;

    /**
     * @Column(type="integer", length=11, nullable=false)
     */
    public $min_free_memory_mb;

    /**
     * @Column(type="integer", length=11, nullable=false)
     */
    public $min_free_memory_percentage;

    /**
     * @Column(type="integer", length=11, nullable=false)
     */
    public $max_cpu_load;

    /**
     * @Column(type="integer", length=1, nullable=false)
     */
    public $status;


    public function getSource()
    {
        return "cron_settings";
    }
}
