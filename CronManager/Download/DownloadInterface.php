<?php
/**
 * @namespace
 */
namespace CronManager\Download;

/**
 * Interface DownloadInterface
 * @package CronManager\Download
 */
interface DownloadInterface
{
	/**
	 * Download contetn by link to path 
	 * 
	 * @param string $resource
	 * @paran string $path
	 * @return bollean
	 */
	public function download($uri, $path);
}