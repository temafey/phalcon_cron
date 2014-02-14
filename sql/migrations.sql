
-- --------------------------------------------------------

--
-- Table structure for table `cron_job`
--

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
  `description` varchar(250) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=9 ;

--
-- Dumping data for table `cron_job`
--

INSERT INTO `cron_job` (`id`, `name`, `command`, `second`, `minute`, `hour`, `day`, `month`, `week_day`, `status`, `ttl`, `max_attempts`, `description`) VALUES
  (2, 'test one', './cron.php test one', '*/10', '*', '*', '*', '*', '*', 0, 0, 5, ''),
  (7, 'test two', './cron.php test two', '*/10', '*', '*', '*', '*', '*', 0, 0, 5, ''),
  (8, 'test three', './cron.php test three', '*/10', '*', '*', '*', '*', '*', 0, 0, 5, '');

-- --------------------------------------------------------

--
-- Table structure for table `cron_process`
--

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `cron_process_log`
--

CREATE TABLE IF NOT EXISTS `cron_process_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `process_id` int(11) NOT NULL,
  `type` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `process_id` (`process_id`),
  KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `cron_settings`
--

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

--
-- Dumping data for table `cron_settings`
--

INSERT INTO `cron_settings` (`id`, `environment`, `max_pool`, `min_free_memory_mb`, `min_free_memory_percentage`, `max_cpu_load`, `action_status`, `status`) VALUES
  (1, 'develop', 20, 0, 10, 40, 1, 1);
