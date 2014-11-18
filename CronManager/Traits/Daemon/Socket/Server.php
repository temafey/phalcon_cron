<?php
namespace CronManager\Traits\Daemon\Socket;

trait Server
{
	/**
	 * Socket file fullname
	 * @var string
	 */
	protected $_socketFile = 'unix:///tmp/php.daemon.sock';
	
	/**
	 * Server socket stream
	 * @var resource
	 */
	private $_socket;
	
	/**
	 * Socket connections
	 * @var array
	 */
	private $_socketConnections = [];
	
	/**
	 * Socket error number
	 * @var integer
	 */
	private $_errno;
	
	/**
	 * Socket error message
	 * @var string
	 */
	private $_errstr;
	
	protected $_socketMaxConnections = 10;
	private $_socketConnectionPrimaryId = 0;
	
	/**
	 * Initialize socket server
	 *
	 * @return void
	 */
	protected function _initSocket()
	{
        $sockFile = sys_get_temp_dir() . '/php.cron.manager.sock';

        if (file_exists($sockFile)) {
            unlink ($sockFile);
        }

        $this->_errno = 0;
		$this->_errstr = '';
		$this->_socket = stream_socket_server($this->_socketFile, $this->_errno, $this->_errstr);
		if (!$this->_socket) {
			echo "Socket not init\n";
			exit(2);
		}
		stream_set_blocking($this->_socket, 0);
	}
	
	/**
	 * Checking for free socket connection
	 * 
	 * @return boolean
	 */
	protected function _existsFreeConnection()
	{
		return !(count($this->_socketConnections) == $this->_socketMaxConnections);
	}
	
	/**
	 * Set new socket connection
	 */
	protected function _setSocketConnection()
	{
		if (!$this->_existsFreeConnection()) {
			echo "Max connections\n";
			return false;
		}
		$connection = stream_socket_accept($this->_socket);
		if (!$connection) {
			echo "Not connectged\n";
			return;
		}
		stream_set_blocking($connection, 0);
		$id = $this->_socketConnectionPrimaryId;
		$this->_socketConnections[] = $connection;		
		++$this->_socketConnectionPrimaryId;
		
		return $id;
	}
	
	/**
	 * Get socket connection by id
	 * 
	 * @param integer $id
	 * @return resource
	 */
	protected function _getSocketConnection($id)
	{
		if (!isset($this->_socketConnections[$id])) {
			return false;
		}
		
		return $this->_socketConnections[$id];
	}
	
	/**
	 * Close socket connection by id
	 *
	 * @param integer $id
	 * @return boolean
	 */
	protected function _closeSocketConnection($id)
	{
		if (!isset($this->_socketConnections[$id])) {
			return false;
		}
		fclose($this->_socketConnections[$id]);
		unset($this->_socketConnections[$id]);
		
		return true;		
	}
	
	/**
	 * Close socket connections
	 *
	 * @return boolean
	 */
	protected function _closeSocketConnections()
	{
		foreach ($this->_socketConnections as $id => $connection) {
			fclose($connection);
			unset($this->_socketConnections[$id]);
		}
	
		return true;
	}
	
	/**
	 * Close socket server
	 *
	 * @return void
	 */
	protected function _closeSocket()
	{
		if ($this->_socket) {
			fclose($this->_socket);
			unlink($this->_socketFile);
		}
	}
}