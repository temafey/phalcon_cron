<?php
/**
 * @namespace
 */
namespace CronManager\Queue\Model;

/**
 * Class Log
 * @package CronManager\Queue\Model
 */
class Log extends \Phalcon\Mvc\Model 
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
    public $process_id;

    /**
     * @Column(type="string", length=100, nullable=false)
     */
    public $type;

    /**
     * @Column(type="string", length=0, nullable=false)
     */
    public $message;

    /**
     * @Column(type="string", length=0, nullable=false)
     */
    public $time;


    /**
     * Initializer method for model.
     */
    public function initialize()
    {        
        $this->belongsTo("process_id", "Process", "id");
    }

    public function getSource()
    {
        return "cron_process_log";
    }
}
