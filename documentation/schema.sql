-- --------------------------------------------------------
-- Host:                         lifedev-01.her.hcmr.gr
-- Server version:               5.7.17-0ubuntu0.16.04.1 - (Ubuntu)
-- Server OS:                    Linux
-- HeidiSQL Version:             9.3.0.4984
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- Dumping structure for table rvlab2.jobs
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_email` varchar(100) NOT NULL,
  `function` varchar(50) NOT NULL,
  `status` varchar(25) NOT NULL,
  `submitted_at` datetime NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `jobsize` int(11) DEFAULT '0',
  `inputs` varchar(500) DEFAULT NULL,
  `parameters` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=150 DEFAULT CHARSET=utf8;

-- Dumping data for table rvlab2.jobs: ~42 rows (approximately)
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;


-- Dumping structure for table rvlab2.jobs_logs
CREATE TABLE IF NOT EXISTS `jobs_logs` (
  `id` int(11) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `function` varchar(50) NOT NULL,
  `status` varchar(25) NOT NULL,
  `submitted_at` datetime NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `jobsize` int(11) DEFAULT '0',
  `inputs` varchar(500) NOT NULL,
  `parameters` varchar(500) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Dumping data for table rvlab2.jobs_logs: ~1 rows (approximately)
/*!40000 ALTER TABLE `jobs_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs_logs` ENABLE KEYS */;


-- Dumping structure for table rvlab2.logs
CREATE TABLE IF NOT EXISTS `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_email` varchar(100) NOT NULL,
  `when` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `controller` varchar(50) NOT NULL,
  `method` varchar(50) NOT NULL,
  `category` varchar(30) NOT NULL,
  `message` varchar(350) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8;

-- Dumping data for table rvlab2.logs: ~90 rows (approximately)
/*!40000 ALTER TABLE `logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `logs` ENABLE KEYS */;


-- Dumping structure for table rvlab2.registrations
CREATE TABLE IF NOT EXISTS `registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_email` varchar(100) NOT NULL,
  `starts` datetime NOT NULL,
  `ends` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

-- Dumping data for table rvlab2.registrations: ~5 rows (approximately)
/*!40000 ALTER TABLE `registrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `registrations` ENABLE KEYS */;


-- Dumping structure for table rvlab2.settings
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sname` varchar(50) NOT NULL,
  `value` varchar(100) NOT NULL,
  `last_modified` datetime NOT NULL,
  `about` varchar(500) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

-- Dumping data for table rvlab2.settings: ~5 rows (approximately)
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` (`id`, `sname`, `value`, `last_modified`, `about`) VALUES
	(2, 'rvlab_storage_limit', '1000000000', '2017-03-31 11:23:02', 'Total available storage space for R vLab users (in KB)'),
	(3, 'max_users_supported', '200', '2017-03-31 11:23:02', 'Maximum active users that can be supported by R vLab (in order for each user to have an adequate storage space).'),
	(4, 'job_max_storagetime', '30', '2017-03-31 11:23:02', 'The maximum period for which a user\'s job is retained (in days). After that period, the job will be automatically be deleted.'),
	(5, 'status_refresh_rate_page', '30000', '2017-03-31 11:23:02', 'How often (in milliseconds) the web page makes an AJAX request to update the information about the status of each job'),
	(6, 'last_errors_to_display', '20', '2017-03-31 09:54:19', 'The number of last errors to display in the relevant administration page');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;


-- Dumping structure for table rvlab2.workspace_files
CREATE TABLE IF NOT EXISTS `workspace_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_email` varchar(200) NOT NULL,
  `filename` varchar(200) NOT NULL,
  `filesize` bigint(20) NOT NULL DEFAULT '0',
  `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8;

-- Dumping data for table rvlab2.workspace_files: ~6 rows (approximately)
/*!40000 ALTER TABLE `workspace_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `workspace_files` ENABLE KEYS */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
