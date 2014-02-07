<?php
/**
 * @namespace
 */
namespace CronManager\Download\Adapter;

use CronManager\Download\DownloadInterface;

/**
 * Class Curl
 * @package CronManager\Download\Adapter
 */
class Curl implements DownloadInterface
{
	/**
	 * Cmd
	 * @var string
	 */
	private $_cmd = 'curl -C ';

	/**
	 * Bin path
	 * @var string
	 */
	private $_binPath = '';
	
	/**
	 * Download
	 *
	 * @param string $url
	 * @param string $path
	 * @return string
	 */
	public function download($url, $path)
	{
		return shell_exec($this->_binPath.$this->_cmd." -o ".$path." '".$url."'");
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