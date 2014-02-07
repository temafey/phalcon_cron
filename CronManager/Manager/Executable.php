<?php
/**
 * @namespace
 */
namespace CronManager\Manager;

use CronManager\Traits\DIaware,
    CronManager\Tools\Observer as Observe,
    CronManager\Traits\Observable,
    CronManager\Traits\Message;

/**
 * Class Executable
 * @package CronManager\Manager
 */
class Executable implements \Phalcon\DI\InjectionAwareInterface
{	
	use DIaware,Observable,Message;
    
	/**
	 * Terminated flag
	 * @var boolean
	 */
	protected $_is_terminated = FALSE;
	
	/**
	 * Cleanup clouser function
	 */
	protected $_cleanup_function = NULL;
	
	/**
	 * Constructor 
	 * 
	 * @param \Phalcon\DiInterface
	 */
	final public function __construct(\Phalcon\DiInterface $dependencyInjector) 
	{
		$this->setDi($dependencyInjector);
		$this->_init();
		
		$executable = $this;
		$handler = function ($signo) use ($executable) {
			$executable->signalHandler($signo);
		};
		pcntl_signal(SIGTERM, $handler);
		pcntl_signal(SIGINT,  $handler);
		pcntl_signal(SIGUSR1, $handler);
		pcntl_signal(SIGUSR2, $handler);
			
		stream_set_blocking(STDIN, 0);
		stream_set_blocking(STDOUT, 0);
		stream_set_blocking(STDERR, 0);
	}
	
	/**
	 * Init function 
	 * 
	 * @return void
	 */
	protected function _init()
	{		
	}
	
	/**
	 * Process user signal
	 * 
	 * @return void
	 */
	public function signalHandler($signo) 
	{
		switch ($signo) {
			case SIGTERM:
			case SIGHUP:
			case SIGINT:
				$this->_is_terminated = TRUE;
				echo "exiting in ".get_class($this)."...\n";
				break;
			case SIGUSR1:
				$this->checkStdin();
				break;
			case SIGUSR2:
				$this->_is_terminated = TRUE;
				echo "[SHUTDOWN] in " . get_class($this) . PHP_EOL;
				flush();
				exit(1);
				break;
			default:
			// handle all other signals
			break;
		}
	}
	
	/**
	 * Get user content and dispatch it
	 * 
	 * @return void
	 */
	protected function checkStdin() 
	{
		$read = array(STDIN);
		$write = NULL;
		$except = NULL;

		if (is_array($read) && count($read) > 0) {
			if (false === ($num_changed_streams = stream_select($read, $write, $except, 1))) {
				// oops
			} elseif ($num_changed_streams > 0) {
				if (is_array($read) && count($read) > 0) {
					// stdin
					$content = '';
					while ($cmd = fgets(STDIN)) {
						if (!$cmd) { 
							break;
						}
						$content .= $cmd;
					}
					$this->dispatch($content);
					//echo "recieved $content";
					//echo "stdin> " . $cmd;
				}
			}
		}
		//usleep(200000);
	}
	
	/**
	 * Calls signal handlers for pending signals
	 * 
	 * @return boolean
	 */
	protected function isTerminated() 
	{
		pcntl_signal_dispatch();
		if ($this->_is_terminated) {
			$this->cleanup();
		}
	
		return $this->_is_terminated;
	}
	
	
	/**
	 * Final handler, if user stop process exec cleanup function
	 * 
	 * @return void
	 */
	private function cleanup() 
	{
		if (is_callable($this->_cleanup_function)) {
			call_user_func($this->_cleanup_function);
		}
	}
	
	/**
	 * Register cleanup handler
	 */
	protected function registerCleanup($callable) 
	{
		if (is_callable($callable)) {
			$this->_cleanup_function = $callable;
		} else {
			trigger_error("$callable is not callable func", E_USER_WARNING);
		}
	}
	
	/**
	 * Command dispatcher
	 * 
	 * @return void
	 */
	protected function dispatch($cmd) 
	{
		if (null !== ($params = json_decode($cmd, true))) {
			$this->_execute($params);
		}		
	}
	
	/**
	 * Execute user command
	 * 
	 * @return void
	 */
	protected function _execute(array $params)
	{
	}
	
	/**
	 * Destructor
	 */
	public function __destruct() 
	{
		//echo "destructor called in " . get_class($this) . PHP_EOL;
		if (!$this->_is_terminated) {
			$this->_is_terminated = TRUE;
			$this->isTerminated();
		}
	}
	
}
