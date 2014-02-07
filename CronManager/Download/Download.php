<?php
/**
 * @namespace
 */
namespace CronManager\Download;

use CronManager\Download\Adapter\AdapterInterface,
    CronManager\Download\Adapter\Curl,
    CronManager\Download\Adapter\Wget;

/**
 * Class Download
 * @package CronManager\Download
 */
class Download implements DownloadInterface
{
	CONST ADAPTER_WGET = 'wget';
	CONST ADAPTER_CURL = 'curl';
	
	/**
	 * Download adapter
	 * @var \CronManager\Download\Adapter\AdapterInterface
	 */
	protected $_adapter;
	
	/**
	 * Initiate
	 * 
	 * @param array $options
	 */
	public function __construct(array $options)
	{
		$this->setOptions($options);
	}	
	
	/**
	 * Set options
	 * 
	 * @param array $options
	 * @return \CronManager\Download\Download
	 */
	public function setOptions(array $options)
	{
		if (array_key_exists('adapter', $options)) {
			$this->setAdapter($options['adapter']);
		}
	}
	
	/**
	 * Set download adapter
	 * 
	 * @param array|string|AdapterInterface $adapter
	 * @return \CronManager\Download\Download
	 */
	public function setAdapter($adapter)
	{
		if ($adapter instanceof AdapterInterface) {
			$this->_adapter = $adapter;
			return $this;
		}
		
		if (is_string($adapter)) {
			switch ($adapter) {
				case Download::ADAPTER_CURL:
					$this->_adapter = new Curl();
					break;
				case Download::ADAPTER_WGET:
					$this->_adapter = new Wget();
					break;
				default:
					throw new \Exception("Download adapter not set!");
					break;
			}
		}
		
		return $this;
	}
	
	/**
	 * Download file
	 * 
	 * @param string $uri
	 * @param string $path
	 * @return boolean
	 */
	public function download($uri, $path)
	{
		return $this->_adapter->download($uri, $path);
	}
}

