<?php 
namespace CronManager\Manager;

use CronManager\Manager\Job\Observer as Observer,
    CronManager\Traits\Observable,
    CronManager\Traits\Message;

// class to execute commands as daemons
class Job 
{
	use Observable,Message;
	
	/**
	 * Process pid
	 * @var integer
	 */
    protected $_pid = 0;
    
    /**
	 * Process name
	 * @var string
     */
    protected $_name;

    /**
	 * Process run command
	 * @var string
     */
    protected $_cmd = '';

    /**
	 * Process error output
	 * @var string
     */
    protected $_stderr = '/dev/null';

    private $_resource = NULL;
    private $_pipes = array();
    private $_waitpid = TRUE;

    /**
	 * Constructor
	 * 
	 * @param string $cmd
	 * @param string $name
     */
    public function __construct($cmd, $name = 'job') 
    {
        $this->_cmd = $cmd;
        $this->_name = $name;
    }

    /**
	 * Destructor
     */
    public function __destruct() 
    {
        // wait process finish
        if ($this->_resource) {
            if ($this->_waitpid && $this->isRunning()) {
                $this->_message = "Waiting for job to complete";
                $this->notify();
                $status = NULL;
                pcntl_waitpid($this->_pid, $status, WNOHANG);
                
                /*while ($this->isRunning()) {
                    echo '.';
                    sleep(1);
                }*/
            }
        }

        // close descriptor
        if (isset($this->_pipes) && is_array($this->_pipes)) {
            foreach (array_keys($this->_pipes) as $index ) {
                if (is_resource($this->_pipes[$index])) {
                    fflush($this->_pipes[$index]);
                    fclose($this->_pipes[$index]);
                    unset($this->_pipes[$index]);
                }
            }
        }

        // close opened handler
        if ($this->_resource) {
            proc_close($this->_resource);
            unset($this->_resource);
        }
        $this->_message = "Stop";
        $this->notify();
    }

    /**
	 * Return process pid
	 * 
	 * @return integer
     */
    public function pid() 
    {
        return $this->_pid;
    }
	
    /**
	 * Return job name
	 * 
	 * @return string
     */
    public function name() 
    {
        return $this->_name;
    }

    /**
	 * Read process messages
	 * 
	 * @return string
     */
    private function readPipe($index, $nohup = FALSE) 
    {
        if (!isset($this->_pipes[$index])) {
        	return FALSE;
        }

        if (!is_resource($this->_pipes[$index]) || feof($this->_pipes[$index])) {
        	return FALSE;
        }
        if ($nohup) {
            $data = '';
            while ($line = fgets($this->_pipes[$index])) {
                $data .= $line;
            }
            $this->_message = $data;
            $this->notify($index);
            
            return $data;
        }

        while ($data = fgets($this->_pipes[$index])) {
          	$this->_message = $data;
          	$this->notify($index);
        }
    }

    /**
	 * Read process messages
	 * 
	 * @return string
     */
    public function pipeline($nohup = FALSE) 
    {
        return $this->readPipe(1, $nohup);
    }

    /**
	 * Read process errors
	 * 
	 * @return string
     */
    public function stderr($nohup = FALSE) 
    {
        return $this->readPipe(2, $nohup);
    }

    /**
	 * Run process and return his pid
	 * 
	 * @return integer
     */
    public function execute() 
    {
        $descriptorspec = array(
            0 => array('pipe', 'r'),  // stdin
            1 => array('pipe', 'w'),  // stdout
            2 => array('pipe', 'w') // stderr 
        );

        $this->_resource = proc_open('exec '.$this->_cmd, $descriptorspec, $this->_pipes);

        // ставим неблокирующий режим всем дескрипторам
        stream_set_blocking($this->_pipes[0], 0); 
        stream_set_blocking($this->_pipes[1], 0);
        stream_set_blocking($this->_pipes[2], 0);

        if (!is_resource($this->_resource)) {
        	return FALSE;
        }

        $proc_status     = proc_get_status($this->_resource);
        $this->_pid      = isset($proc_status['pid']) ? $proc_status['pid'] : 0;
        
        $this->_message = "Start";
        $this->notify();
        
        return $this->_pid;
    }
    
    /**
	 * Terminate process
	 * 
	 * @return boolean
     */
    public function terminate()
    {
    	// close descriptor
    	if (isset($this->_pipes) && is_array($this->_pipes)) {
    		foreach (array_keys($this->_pipes) as $index ) {
    			if (is_resource($this->_pipes[$index])) {
    				fflush($this->_pipes[$index]);
    				fclose($this->_pipes[$index]);
    				unset($this->_pipes[$index]);
    			}
    		}
    	}
    	
    	// close opened handler
    	if ($this->_resource) {
    		proc_terminate($this->_resource);
    		unset($this->_resource);
    	}
    	$this->_message = "Process terminated";
    	$this->notify();
    	
    	return true;
    }
    
    /**
	 * Terminate process
	 * 
	 * @return boolean
     */
    public function kill()
    {
    	// close descriptor
    	if (isset($this->_pipes) && is_array($this->_pipes)) {
    		foreach (array_keys($this->_pipes) as $index ) {
    			if (is_resource($this->_pipes[$index])) {
    				fflush($this->_pipes[$index]);
    				fclose($this->_pipes[$index]);
    				unset($this->_pipes[$index]);
    			}
    		}
    	}
    	
    	// close opened handler
    	if ($this->_resource) {
    		posix_kill($this->_pid, 9);
    		unset($this->_resource);
    	}
    	$this->_message = "Process killed";
    	$this->notify();
    	
    	return true;
    }

    public function getPipe() 
    {
        return $this->_pipes[1];
    }

    public function getStderr() 
    {
        return $this->_pipes[2];
    }

    /**
	 * Check if process runninig or not
	 * 
	 * @return boolean
     */
    public function isRunning() 
    {
        if (!is_resource($this->_resource)) { 
        	return FALSE;
        }
        $proc_status = proc_get_status($this->_resource);
        
        return isset($proc_status['running']) && $proc_status['running'];
    }
    
    /**
	 * Return job status
	 * 
	 * @return array
     */
    public function getStatus()
    {
    	if (!is_resource($this->_resource)) {
    		return FALSE;
    	}
    	
    	return proc_get_status($this->_resource);
    }
    
    /**
	 * Return job linux status
	 * 
	 * @return array
     */
    public function getStatus2()
    {
    	//exec("ps -p $this->_pid -o pid,vsz=MEMORY -o user,group=GROUP -o comm,args=ARGS, -o stime,etime=RUNNING", $str);
    	exec("ps -p $this->_pid -o pid,vsz=MEMORY -o user,group=GROUP -o stime,etime=TIME", $str);
    	
    	$args = preg_split('/ +/', $str[0]);
    	$results = preg_split('/ +/', $str[1]);
    	if (empty($results[0])) {
    		unset($results[0]);
    	}
    	$args = ['pid', 'memory', 'user', 'group', 'stime', 'time'];

    	return array_combine($args, $results);
    }

    // посылка сигнала процессу
    public function signal($sig) 
    {
        if (!$this->isRunning()) { 
        	return FALSE;
        }
        posix_kill($this->_pid, $sig);
    }

    // отправка сообщения в STDIN процесса
    public function message($msg) 
    {
        if (!$this->isRunning()) { 
        	return FALSE;
        }
        fwrite($this->_pipes[0], $msg);     
    }
    
    /**
	 * Observer factory method
	 * 
	 * @param array $params
	 * @return \CronManager\Traits\Observer
     */
    public function observerFactory(array $params)
    {
    	switch ($params['observer']) {
    		case 'mysql':
    			$options = (array_key_exists('options', $params)) ? $params['options'] : array();
    			return new Observer\Mysql($params['hash'], $this->_cmd, $this->_pid, $params['job_id'], $this->_name, $options);
    		case 'file':
    			$options = (array_key_exists('options', $params)) ? $params['options'] : array();
    			return new Observer\File($params['hash'], $this->_cmd, $this->_pid, $params['job_id'], $this->_name, $options);    			
    	}
    } 
}