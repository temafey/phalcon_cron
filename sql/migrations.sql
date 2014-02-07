-- --------------------------------------------------------

--
-- Table structure for table `cron_job`
--

DROP TABLE IF EXISTS `cron_job`;
CREATE TABLE IF NOT EXISTS `cron_job` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `command` varchar(200) NOT NULL,
  `second` varchar(100) NOT NULL,
  `minute` varchar(100) NOT NULL,
  `hour` varchar(100) NOT NULL,
  `day` varchar(100) NOT NULL,
  `month` varchar(100) NOT NULL,
  `week_day` varchar(100) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `ttl` int(11) NOT NULL DEFAULT '0',
  `max_attempts` tinyint(2) NOT NULL DEFAULT '5',
  `desc` varchar(250) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `cron_process`
--

DROP TABLE IF EXISTS `cron_process`;
CREATE TABLE IF NOT EXISTS `cron_process` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `hash` varchar(40) NOT NULL,
  `command` varchar(255) NOT NULL,
  `action` varchar(40) NOT NULL,
  `pid` int(6) NOT NULL,
  `status` enum('run','running','completed','aborted','error','stopped','stop','waiting','finished') NOT NULL,
  `stime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `time` int(11) NOT NULL DEFAULT '0',
  `phash` varchar(40) NOT NULL DEFAULT '1',
  `attempt` tinyint(2) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `hash` (`hash`),
  KEY `phash` (`phash`,`status`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `cron_process_log`
--

DROP TABLE IF EXISTS `cron_process_log`;
CREATE TABLE IF NOT EXISTS `cron_process_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `process_id` int(11) NOT NULL,
  `type` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `process_id` (`process_id`),
  KEY `time` (`time`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `cron_settings`
--

DROP TABLE IF EXISTS `cron_settings`;
CREATE TABLE IF NOT EXISTS `cron_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `environment` varchar(200) NOT NULL,
  `max_pool` int(11) NOT NULL DEFAULT '10',
  `min_free_memory_mb` int(11) NOT NULL DEFAULT '0',
  `min_free_memory_percentage` int(11) NOT NULL DEFAULT '10',
  `max_cpu_load` int(11) NOT NULL DEFAULT '40',
  `action_status` smallint(2) NOT NULL DEFAULT '1',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
