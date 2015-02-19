<?php
/**
 * @namespace
 */
namespace CronManager\Tools\Stuff;

/**
 * Class System
 * @package Tools
 * @category Staff
 */
class System
{
    /**
     * Return script used memory in megabytes
     *
     * @return float
     */
    public static function getUsedMemory()
    {
        $mem_usage = memory_get_usage();
        return round($mem_usage / 1048576, 2);
    }

    /**
     * Check using RAM
     *
     * @return bool
     */
    public static function checkUsedMemory($limit = 128)
    {
        $usedMemory = static::getUsedMemory();
        if ($usedMemory > $limit) {
            return false;
        }

        return true;
    }

}