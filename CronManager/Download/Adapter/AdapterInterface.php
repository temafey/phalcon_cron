<?php
/**
 * @namespace
 */
namespace CronManager\Download\Adapter;

/**
 * Interface AdapterInterface
 * @package CronManager\Download\Adapter
 */
interface AdapterInterface
{
	/**
	 * Download content by url to path
	 * 
	 * @param string|array|\stdClass $uri
	 * @param string $path
	 * @return boolean
	 */
	public function download($uri, $path);
}