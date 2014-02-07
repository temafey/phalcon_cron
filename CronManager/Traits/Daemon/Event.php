<?php
/**
 * @namespace
 */
namespace CronManager\Traits\Daemon;

/**
 * Class Event
 * @package CronManager\Traits\Daemon
 */
trait Event
{

	/**
	 * Event base resource
	 * @var resource
	 */
	private $_eventBase;
	
	/**
	 * Events
	 * @var array
	 */
	private $_events = [];
	
	/**
	 * Event buffers
	 * @var array
	 */
	private $_eventBuffers = [];
	
	/**
	 * Event descriptions
	 * @var array
	 */
	private $_DESCRIPTORS = [
		'timeout' =>  EV_TIMEOUT,
		'signal' => EV_SIGNAL,
		'read' => EV_READ,
		'write' => EV_WRITE,
		'persist' => EV_PERSIST
	];
	
	/**
	 * Statistic event
	 * @var event
	 */
	private $_eventTimer;
	
	/**
	 * Statistic event timeout in microseconds
	 * @var integer
	 */
	protected $_eventTimerInterval = 1000000;
	
	/**
	 * Initialize event base
	 *
	 * @return void
	 */
	protected function _initEventBase()
	{
		$this->_eventBase = event_base_new();
	}
	
	/**
	 * Set new event
	 *
	 * @param string $name
	 * @param resource $resource
	 * @param string $descriptor
	 * @param string|array $handler
	 * @return void
	 */
	protected function _setEvent($name, $resource, $descriptor, $handler)
	{
		if (isset($this->_events[$name]) || $handler === null) {
			return false;
		}
		$event = event_new();
		event_set($event, $resource, $descriptor, $handler);
		$res = event_base_set($event, $this->_eventBase);
		event_add($event);
		
		$this->_events[$name] = $event;
	}	
	
	/**
	 * Close event by name
	 *
	 * @param integer $id
	 * @return boolean
	 */
	protected function _closeEvent($name)
	{
		if (!isset($this->_events[$name])) {
			return false;
		}
		event_del($this->_events[$name]);
		event_free($this->_events[$name]);
		unset($this->_events[$name]);
		
		return true;		
	}
	
	/**
	 * Set new event buffer
	 * 
	 * @param integer $id 
	 * @param resource $resource 
	 * @param string|array $onRead
	 * @param string|array $onWrite
	 * @param string|array $onError
	 * @return void
	 */
	protected function _setEventBuffer($id, $resource, $onRead, $onWrite, $onError, $descriptor)
	{
		$buffer = event_buffer_new($resource, $onRead, $onWrite, $onError, $id);
		event_buffer_base_set($buffer, $this->_eventBase);
		event_buffer_timeout_set($buffer, 30, 30);
		event_buffer_watermark_set($buffer, EV_READ, 0, 0xffffff);
		event_buffer_priority_set($buffer, 10);
		event_buffer_enable($buffer, $descriptor);

		$this->_eventBuffers[$id] = $buffer;
	}
	
	/**
	 * Get event buffer by id
	 * 
	 * @param integer $id
	 * @return resource
	 */
	protected function _getEventBuffer($id)
	{
		if (!isset($this->_eventBuffers[$id])) {
			return false;
		}
		
		return $this->_eventBuffers[$id];
	}
	
	/**
	 * Read from buffer by id
	 * 
	 * @param integer $id
	 * @param integer $length
	 * @return string|boolean
	 */
	protected function _readEventBuffer($id, $length)
	{
		if (!isset($this->_eventBuffers[$id])) {
			return false;
		}
		$content = '';
		while($read = event_buffer_read($this->_eventBuffers[$id], $length)) {
			$content .= $read;
		}
		
		return $content;
	}
	
	/**
	 * Close event buffer by id
	 *
	 * @param integer $id
	 * @param string $descriptor
	 * @return boolean
	 */
	protected function _closeEventBuffer($id, $descriptor = null)
	{
		if (!isset($this->_eventBuffers[$id])) {
			return false;
		}
		event_buffer_disable($this->_eventBuffers[$id], $descriptor);
		event_buffer_free($this->_eventBuffers[$id]);
		unset($this->_eventBuffers[$id]);
		
		return true;		
	}
	
	/**
	 * Handle events
	 * 
	 * return void
	 */
	protected function _eventLoop()
	{
		event_base_loop($this->_eventBase, EVLOOP_NONBLOCK);
	}
	
	/**
	 * Close events
	 *
	 * @return void
	 */
	private function _closeEvents()
	{
		foreach ($this->_events as $name => $event) {
			event_del($event);
			event_free($event);
			unset($this->_events[$name]);
		}
		if ($this->_eventTimer) {
			event_del($this->_eventTimer);
			event_free($this->_eventTimer);
			$this->_eventTimer = false;
		}
		if ($this->_eventBase) {
			event_base_free($this->_eventBase);
		}
	}
	
	/**
	 * Close event buffers
	 *
	 * @return void
	 */
	private function _closeBuffers()
	{
		foreach ($this->_eventBuffers as $id => $buffer) {
			event_buffer_disable($buffer);
			event_buffer_free($buffer);
			unset($this->_eventBuffers[$id]);
		}
	}
	
	/**
	 * Return handler
	 *
	 * @return \Closure
	 */
	protected function _timerEventHandler()
	{
		$server = $this;
		$handler = function ($tmpfile, $flag ,$interval) use ($server) {
			$server->onTimer($tmpfile, $flag, $interval);
		};
		
		return [$this, 'onTimer'];
	}
	
	/**
	 * Initialize statistic event
	 *
	 * @return boolean
	 */
	protected function _initTimer()
	{
		$tmpfile = tmpfile();
		$this->_eventTimer = event_new();		
		event_set($this->_eventTimer, $tmpfile, 0, $this->_timerEventHandler(), $this->_eventTimerInterval);
		$res = event_base_set($this->_eventTimer, $this->_eventBase);
		
		return event_add($this->_eventTimer, $this->_eventTimerInterval);
	}
	
	/**
	 * Statistic event callback
	 *
	 * @return void
	 */
	public function onTimer($tmpfile, $flag, $interval) 
	{	
		if ($this->_eventTimer) {
			event_del($this->_eventTimer);
			event_free($this->_eventTimer);
		}
		$this->_eventStatistic();		
	
		$this->_eventTimer = event_new();
		event_set($this->_eventTimer, $tmpfile, 0, $this->_timerEventHandler(), $interval);
		$res = event_base_set($this->_eventTimer, $this->_eventBase);
		
		return event_add($this->_eventTimer, $interval);
	}
	
	/**
	 * Event timer function
	 * 
	 * @return void
	 */
	protected function _eventStatistic()
	{
		
	}
}