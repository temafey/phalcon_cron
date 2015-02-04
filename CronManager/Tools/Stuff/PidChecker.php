<?php

namespace CronManager\Tools\Stuff;

class PidChecker
{
    /**
     * Check is daemon process running, if not delete is exists socket and pid files
     *
     * @param string $pidFile
     * @return bool
     */
    public static function checkIsDaemonRunning($pidFile)
    {
        if (file_exists($pidFile)) {
            if (!static::pidExists(trim(file_get_contents($pidFile)))) {
                return false;
            }
        } else {
            return false;
        }

        return true;
    }

    /**
     * Checks if a process is running using 'exec(ps $pid)'.
     *
     * @param PID of Process
     * @return Boolean true == running
     */
    public static function pidExists($pId)
    {
        if (!$pId) {
            return false;
        }

        $linesOut = [];
        exec('ps '.(int) $pId, $linesOut);
        if (count($linesOut) >= 2) {
            return true;
        }

        return false;
    }
}