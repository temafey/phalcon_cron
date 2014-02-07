<?php
/**
 * @namespace
 */
namespace CronManager\Traits;

/**
 * Class Message
 * @package CronManager\Traits
 */
trait Message 
{
	/**
	 * Message
	 * @var string
	 */
	protected $_message;	
	
	/**
	 * Return job message
	 *
	 * @return string
	 */
	public function getMessage()
	{
		return $this->_message;
	}
    
    /**
	 * Set notify message
	 * 
	 * @param string $message
	 * @return \CronManager\Manager\Job
     */
    public function setMessage($message)
    {
    	$this->_message = $message;
    	return $this;
    }
}