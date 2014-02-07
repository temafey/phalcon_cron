<?php
/**
 * @namespace
 */
namespace CronManager\Http\Client;

/**
 * Class Curl
 * @package CronManager\Http\Client
 */
class Curl
{	
	protected static $handles = array();

	public static function getWebPage($url, $user='', $pass='')
	{
		$options = array(
			CURLOPT_RETURNTRANSFER => true,     // return web page
			CURLOPT_USERPWD        => "$user:$pass",
			CURLOPT_HEADER         => false,    // don't return headers
			CURLOPT_FOLLOWLOCATION => true,     // follow redirects
			CURLOPT_ENCODING       => "",       // handle all encodings
//			CURLOPT_USERAGENT      => "tieste", // who am i
			CURLOPT_AUTOREFERER    => true,     // set referer on redirect
			CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
			CURLOPT_TIMEOUT        => 120,      // timeout on response
			CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
		);

		$ch      = curl_init($url);
		curl_setopt_array($ch, $options);
		$content = curl_exec($ch);
		$err     = curl_errno($ch);
		$errmsg  = curl_error($ch);
		$header  = curl_getinfo($ch);
		curl_close($ch);

		$header['errno']   = $err;
		$header['errmsg']  = $errmsg;
		$header['content'] = $content;
		
		return $header;
	}
	
	public static function downloadFile($url, $path, $user='', $pass='', $progressCallback=null)
	{
		$fp = fopen($path, 'w+');
		
		$options = array(
//			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_FILE => $fp,
			CURLOPT_USERPWD => "$user:$pass",
			CURLOPT_BUFFERSIZE => 32768
		);
		
		if ($progressCallback) {
			$options[CURLOPT_NOPROGRESS] = false;
			$options[CURLOPT_PROGRESSFUNCTION] = $progressCallback;
		}
		
		$ch = curl_init($url);
		curl_setopt_array($ch, $options);
				
		$result = curl_exec($ch);
		curl_close($ch);
		fclose($fp);
		
		return $result;
	}
	
	public static function getFileTimestamp($path, $user='', $pass='')
	{
		$options = array(
			CURLOPT_NOBODY => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			CURLOPT_USERPWD => "$user:$pass"
		);
		
		$ch = curl_init($path);
		curl_setopt_array($ch, $options);
		
		$data = curl_exec($ch);
		curl_close($ch);
		
		if (preg_match('/Last-Modified: ([0-9a-zA-Z:, ]+)/', $data, $matches))
			return $matches['0'];
		
		return null;
	}
	
	public static function getFilesize($path, $user='', $pass='')
	{
		$options = array(
			CURLOPT_RETURNTRANSFER => TRUE,
			
			CURLOPT_NOBODY => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			CURLOPT_USERPWD => "$user:$pass"
		);
		
		$ch = curl_init($path);
		curl_setopt_array($ch, $options);
		
		$data = curl_exec($ch);
		$size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
		
		if (!$size || $size == -1) {
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if ($http_code == 301 || $http_code == 302)	{
				list($header) = explode("\r\n\r\n", $data, 2);
				$matches = [];
				preg_match('/Location:(.*?)\n/', $header, $matches);
				$url = @parse_url(trim(array_pop($matches)));
				if (!$url) {
					//couldn't process the url to redirect to
					$curl_loops = 0;
					return null;
				}
				$last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
				if (!isset($url['scheme'])) {
					$url['scheme'] = $last_url['scheme'];
				}
				if (!isset($url['host'])) {
					$url['host'] = $last_url['host'];
				}
				if (!isset($url['path'])) {
					$url['path'] = $last_url['path'];
				}
				$new_url = $url['scheme'] . '://' . $url['host'] . $url['path'] . (isset($url['query']) ? '?'.$url['query']:'');
				curl_setopt($ch, CURLOPT_URL, $new_url);
				$data = curl_exec($ch);
				$size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
			}
		}
		curl_close($ch);
		/*if (preg_match('/Content-Length: (\d+)/', $data, $matches)) {
			return (int)$matches[1];
		}*/
		
		return ($size == -1) ? null : $size;
	}
	
	public static function clearStreams()
	{
		foreach (self::$handles as $handle) curl_close ($handle);
	}
}