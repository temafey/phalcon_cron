<?php
/**
 * @namespace
 */
namespace CronManager\Queue\Job\Queue;

use CronManager\Manager\Executable;

/**
 * Class Base
 * @package Cron\Job
 */
abstract class Base extends Executable
{

    /**
     * Check process status
     *
     * @param string $hash
     * @param string $status
     * @return boolean
     */
    protected function _check($hash, $status)
    {
        return (\CronManager\Queue\Model\Process::count("hash = '".$hash."' AND status = '".$status."'") == 1)  ? true : false;
    }

    /**
     * Find process by hash
     *
     * @return \CronManager\Queue\Model\Process
     */
    protected function _findByHash($hash, $count = 1)
    {
        $process = \CronManager\Queue\Model\Process::findFirst("hash = '".$hash."'");
        if (!$process) {
            $db = $this->_di->get('db');
            $db->connect();
            $process = \CronManager\Queue\Model\Process::findFirst("hash = '".$hash."'");
            if (!$process) {
                $this->_message = "Process by hash '".$hash."' not found, try again!";
                $this->notify();
                sleep(1);
                if ($count == 5) {
                    throw new \Exception("Process by hash '".$hash."' not found after ".$count." attemps!");
                }
                return $this->_findByHash($hash, ++$count);
            }
        }

        return $process;
    }


    /**
     * Find process by hash
     *
     * @param string $hash
     * @param string $statэяus
     * @return boolean
     */
    protected function _updateStatus($hash, $status)
    {
        $process = $this->_findByHash($hash);
        sleep(1);
        $process->status = $status;
        return $process->save();
    }

    /**
     * Return process id by hash
     *
     * @param $hash
     * @return mixed
     */
    protected function _getProcessId($hash)
    {
        $process = $this->_findByHash($hash);
        return $process->id;
    }
}