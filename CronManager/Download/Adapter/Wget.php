<?php
/**
 * @namespace
 */
namespace CronManager\Download\Adapter;

use CronManager\Download\DownloadInterface;

/**
 * Class Wget
 * @package CronManager\Download\Adapter
 */
class Wget implements DownloadInterface
{
	
	//wget -c -t 0 --timeout=15
	//wget --ftp-user=USERNAME --ftp-password=PASSWORD DOWNLOAD-URL
	/**
	 * Cmd
	 * @var string
	 */
	private $_cmd = 'wget -c'; //-b in background';

	/**
	 * Bin path
	 * @var string
	 */
	private $_binPath = '';
	
	/**
	 * Download
	 * 
	 * @param string|array|\stdClass $uri
	 * @param string $path
	 * @return string
	 */
	public function download($uri, $path)
	{
		$cmd = $this->_binPath.$this->_cmd;
		$log = "";
		if (is_string($uri)) {
			$cmd .= ' "'.$uri.'"';
		} elseif (is_array($uri)) {
			if (isset($uri['url'])) {
				$cmd .= ' "'.$uri['url'].'"';
			}
			if (isset($uri['user'])) {
				$cmd .= ' --user='.$uri['user'];
			}
			if (isset($uri['pass'])) {
				$cmd .= ' --password='.$uri['pass'];
			}
			if (isset($uri['log'])) {
				$log = "-o ".$uri['log'];
			}
		} elseif (is_object($uri)) {
			if (isset($uri->url)) {
				$cmd .= ' "'.$uri->url.'"';
			}
			if (isset($uri->user)) {
				$cmd .= ' --user='.$uri->user;
			}
			if (isset($uri->pass)) {
				$cmd .= ' --password='.$uri->pass;
			}
			if (isset($uri->log)) {
				$log = "-o ".$uri->log;
			}
		}
		
		return shell_exec($cmd.' -P '.$path." ".$log);
	}
	
	/**
	 * Set bin path
	 * 
	 * @param string $path
	 * @return \CronManager\Download\Adapter\Wget
	 */
	public function setBinPath($path)
	{
		$this->_binPath = rtrim($path, '/').'/';
		return $this;
	}
}