<?php
/**
 * @namespace
 */
namespace CronManager\Queue\Model;

/**
 * Class Process
 * @package CronManager\Queue\Model
 */
class Process extends \Phalcon\Mvc\Model 
{

    /**
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    public $id;

    /**
     * @Column(type="integer", length=11, nullable=false)
     */
    public $job_id;

    /**
     * @Column(type="string", length=40, nullable=false)
     */
    public $hash;

    /**
     * @Column(type="string", length=40, nullable=false)
     */
    public $command;

    /**
     * @Column(type="string", length=40, nullable=false)
     */
    public $action;

    /**
     * @Column(type="integer", length=6, nullable=false)
     */
    public $pid;

    /**
     * @Column(type="string", length=0, nullable=false)
     */
    public $status;

    /**
     * @Column(type="string", length=0, nullable=false)
     */
    public $stime;

    /**
     * @Column(type="integer", length=11, nullable=false)
     */
    public $time;

    /**
     * @Column(type="string", length=40, nullable=false)
     */
    public $phash;

    /**
     * @Column(type="integer", length=2, nullable=false)
     */
    public $attempt;


    /**
     * Initializer method for model.
     */
    public function initialize()
    {        
        $this->belongsTo("job_id", "Job", "id");
    }

    public function getSource()
    {
        return "cron_process";
    }
}
