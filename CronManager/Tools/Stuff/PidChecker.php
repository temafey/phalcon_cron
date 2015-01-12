<?php

namespace CronManager\Tools\Stuff;

class PidChecker
{
    public function checkPidExists($pidFile) {
        $sockFile = sys_get_temp_dir() . '/php.apppicker-cron2.manager.sock';

        if (file_exists($pidFile)) {
            if (!$this->pidExists(file_get_contents($pidFile))) {
                unlink ($sockFile);
            }
        } else {
            unlink ($sockFile);
        }
    }

    /**
     * Checks if a process is running using 'exec(ps $pid)'.
     * @param PID of Process
     * @return Boolean true == running
     */
    public function pidExists($pId)
    {
        if (!$pId)
            return false;

        $linesOut = array();
        exec('ps '.(int)$pId, $linesOut);
        if(count($linesOut) >= 2) {
            return true;
        }
        return false;
    }
}