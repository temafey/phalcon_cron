<?php
/**
 * @namespace
 */
namespace CronManager\Traits\Daemon\Socket;

/**
 * Class Client
 * @package CronManager\Traits\Daemon\Socket
 */
trait Client
{
	/**
	 * Socket file fullname
	 * @var string
	 */
	protected $_socketFile = 'unix:///tmp/php.daemon.sock';
	
	/**
	 * Client socket stream
	 * @var resource
	 */
	private $_socket;

	/**
	 * Socket error number
	 * @var integer
	 */
	private $_socketErrno;
	
	/**
	 * Socket error message
	 * @var string
	 */
	private $_socketErrstr;
	
	/**
	 * Init socket client
	 * 
	 * @return void
	 */
	protected function _initClient()
	{
		$this->_socket = stream_socket_client($this->_socketFile, $this->_socketErrno, $this->_socketErrstr, 5);
		stream_set_blocking($this->_socket, 0);
	}
	
	/**
	 * Write to server
	 * 
	 * @return void
	 */
	public function write($message)
	{
		if (!$this->_socket) {
			$this->_initClient();
		}
		fwrite($this->_socket, $message);
 		$this->_closeClient();
	}
	
	/**
	 * Read from server
	 * 
	 * @return string
	 */
	public function read()
	{
		if (!$this->_socket) {
			$this->_initClient();
		}
		$content = '';
		while (!feof($this->_socket)) {
			$content .= fgets($this->_socket, 128);
		}
		$this->_closeClient();
		
		return $content;
	}
	
	protected function _closeClient()
	{
		if (!$this->_socket) {
			return;
		}
		fclose($this->_socket);
		$this->_socket = false;
	}
}
