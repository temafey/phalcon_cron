<?php
/**
 * @namespace
 */
namespace CronManager\Ftp\Directory;

use Countable,
    SeekableIterator,
    ArrayAccess,
    CronManager\Ftp\Exception,
    CronManager\Ftp\Directory,
    CronManager\Ftp\File;

/**
 * Class Iterator
 * @package CronManager\Ftp\Directory
 */
class Iterator implements SeekableIterator, Countable, ArrayAccess
{
	/**
	 * The directory
	 *
	 * @var string
	 */
	protected $_dir = null;

	/**
	 * The converted files and folders
	 *
	 * @var array
	 */
	protected $_rows = array();
	 
	/**
	 * The raw files and folders
	 *
	 * @var array
	 */
	protected $_data = array();
	 
	/**
	 * The FTP connection
	 *
	 * @var \CronManager\Ftp\Ftp
	 */
	protected $_ftp = null;
	 
	/**
	 * The number of rows
	 *
	 * @var int
	 */
	protected $_count = 0;
	 
	/**
	 * The iterator pointer
	 *
	 * @var int
	 */
	protected $_pointer = 0;
	 
	/**
	 * Instantiate
	 *
	 * @param string $dir The full path
	 * @param \CronManager\Ftp\Ftp $ftp The FTP connection
	 */
	public function __construct($dir, Ftp $ftp)
	{
		$this->_dir = $dir;
		$this->_filter = $filter;
		$this->_ftp = $ftp;
		 
		$lines = @ftp_rawlist($this->_ftp->getConnection(), $dir);
		 
		foreach ($lines as $line) {
			preg_match('/^([\-dl])([rwx\-]+)\s+(\d+)\s+(\w+)\s+(\w+)\s+(\d+)\s+(\w+\s+\d+\s+[\d\:]+)\s+(.*)$/', $line, $matches);
			 
			list($trash, $type, $permissions, $unknown, $owner, $group, $bytes, $date, $name) = $matches;
			 
			if ($type != 'l') {
				$this->_data[] = array(
						'type' => $type,
						'permissions' => $permissions,
						'bytes' => $bytes,
						'name' => $name,
				);
			}
		}
		 
		$this->_count = count($this->_data);
	}
	 
	/**
	 * Rewind the pointer, required by Iterator
	 *
	 * @return \CronManager\Ftp\Ftp\Directory_Iterator
	 */
	public function rewind()
	{
		$this->_pointer = 0;
		 
		return $this;
	}
	 
	/**
	 * Get the current row, required by iterator
	 *
	 * @return \CronManager\Ftp\Ftp\Directory|CronManager\Ftp\Ftp\File|null
	 */
	public function current()
	{
		if ($this->valid() === false) {
			return null;
		}

		if (empty($this->_rows[$this->_pointer])) {
			$row = $this->_data[$this->_pointer];
			switch ($row['type']) {
				case 'd': // Directory
					$this->_rows[$this->_pointer] = new Directory($this->_dir . $row['name'] . '/', $this->_ftp, array('bytes' => $row['bytes'], 'permissions' => $row['permissions']));
					break;
				case '-': // File
					$this->_rows[$this->_pointer] = new File($this->_dir . $row['name'], $this->_ftp, array('bytes' => $row['bytes'], 'permissions' => $row['permissions']));
					break;
				case 'l': // Symlink
				default:
			}
		}
		 
		return $this->_rows[$this->_pointer];
	}
	 
	/**
	 * Return the key of the current row, required by iterator
	 *
	 * @return integer
	 */
	public function key()
	{
		return $this->_pointer;
	}

	/**
	 * Continue the pointer to the next row, required by iterator
	 */
	public function next()
	{
		++$this->_pointer;
	}
	 
	/**
	 * Whether or not there is another row, required by iterator
	 *
	 * @return boolean
	 */
	public function valid()
	{
		return $this->_pointer < $this->_count;
	}
	 
	/**
	 * Return the number of rows, required by countable
	 *
	 * @return integer
	 */
	public function count()
	{
		return $this->_count;
	}

	/**
	 * Seek to the given position, required by seekable
	 *
	 * @param int $position
	 * @return \CronManager\Ftp\Ftp\Directory_Iterator
	 */
	public function seek($position)
	{
		$position = (int)$position;
		if ($position < 0 || $position >= $this->_count) {
			throw new Exception('Illegal index ' . $position);
		}
		$this->_pointer = $position;
		 
		return $this;
	}

	/**
	 * Whether or not the offset exists, required by seekable
	 *
	 * @param int $offset
	 * @return boolean
	 */
	public function offsetExists($offset)
	{
		return isset($this->_data[(int)$offset]);
	}

	/**
	 * Get the item at the given offset, required by seekable
	 *
	 * @param int $offset
	 * @return \CronManager\Ftp\Ftp\Directory|CronManager\Ftp\Ftp\File|null
	 */
	public function offsetGet($offset)
	{
		$this->_pointer = (int)$offset;
		 
		return $this->current();
	}

	/**
	 * Set the item at the given offset (ignored), required by seekable
	 *
	 * @param int $offset
	 * @param mixed $value
	 */
	public function offsetSet($offset, $value)
	{
	}

	/**
	 * Unset the item at the given offset (ignored), required by seekable
	 *
	 * @param int $offset
	 */
	public function offsetUnset($offset)
	{
	}

	/**
	 * Get a given row, required by seekable
	 *
	 * @param int $position
	 * @param boolean $seek [optional]
	 * @return \CronManager\Ftp\Ftp\Directory|CronManager\Ftp\Ftp\File|null
	 */
	public function getRow($position, $seek = false)
	{
		$key = $this->key();
		try {
			$this->seek($position);
			$row = $this->current();
		} catch (Exception $e) {
			throw new Exception('No row could be found at position ' . (int)$position);
		}
		if ($seek == false) {
			$this->seek($key);
		}
		 
		return $row;
	}
}