<?php
/**
 * @namespace
 */
namespace CronManager\Queue\Model;

/**
 * Class Job
 * @package CronManager\Queue\Model
 */
class Job extends \Phalcon\Mvc\Model 
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
    public $name;

    /**
     * @Column(type="string", length=200, nullable=false)
     */
    public $command;

    /**
     * @Column(type="string", length=100, nullable=false)
     */
    public $second;

    /**
     * @Column(type="string", length=100, nullable=false)
     */
    public $minute;

    /**
     * @Column(type="string", length=100, nullable=false)
     */
    public $hour;

    /**
     * @Column(type="string", length=100, nullable=false)
     */
    public $day;

    /**
     * @Column(type="string", length=100, nullable=false)
     */
    public $month;

    /**
     * @Column(type="string", length=100, nullable=false)
     */
    public $week_day;

    /**
     * @Column(type="integer", length=1, nullable=false)
     */
    public $status;

    /**
     * @Column(type="integer", length=11, nullable=false)
     */
    public $ttl;

    /**
     * @Column(type="integer", length=2, nullable=false)
     */
    public $max_attempts;

    /**
     * @Column(type="string", length=250, nullable=false)
     */
    public $desc;


    /**
     * Initializer method for model.
     */
    public function initialize()
    {        
        $this->belongsTo("process_id", "Process", "id");
    }

    public function getSource()
    {
        return "cron_job";
    }
}
