-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 25, 2026 at 11:43 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ncc_feedback_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `get_feedback_stats` ()   BEGIN
    -- Compliments stats
    SELECT 
        COUNT(*) as total_compliments,
        SUM(CASE WHEN response IS NOT NULL AND response != '' THEN 1 ELSE 0 END) as responded_compliments,
        ROUND((SUM(CASE WHEN response IS NOT NULL AND response != '' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as response_rate
    FROM compliments;
    
    -- Complaints stats
    SELECT 
        COUNT(*) as total_complaints,
        SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved_complaints,
        SUM(CASE WHEN status != 'Resolved' THEN 1 ELSE 0 END) as pending_complaints
    FROM complaints;
    
    -- Current quarter stats
    SELECT 
        QUARTER(CURDATE()) as current_quarter,
        COUNT(CASE WHEN type = 'Compliment' THEN 1 END) as current_quarter_compliments,
        COUNT(CASE WHEN type = 'Complaint' THEN 1 END) as current_quarter_complaints
    FROM feedback_summary
    WHERE QUARTER(date) = QUARTER(CURDATE())
    AND YEAR(date) = YEAR(CURDATE());
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` varchar(50) NOT NULL,
  `ref` varchar(20) NOT NULL,
  `date` date NOT NULL,
  `quarter` varchar(5) DEFAULT NULL,
  `client` varchar(255) NOT NULL,
  `text` text NOT NULL,
  `source` varchar(50) DEFAULT 'Facebook',
  `assignee` varchar(255) DEFAULT NULL,
  `response` text DEFAULT NULL,
  `action` text DEFAULT NULL,
  `status` enum('Pending','In Progress','Resolved') DEFAULT 'Pending',
  `response_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `complaints`
--
DELIMITER $$
CREATE TRIGGER `set_complaint_quarter` BEFORE INSERT ON `complaints` FOR EACH ROW BEGIN
    SET NEW.quarter = CONCAT(
        CASE QUARTER(NEW.date)
            WHEN 1 THEN '1st'
            WHEN 2 THEN '2nd'
            WHEN 3 THEN '3rd'
            WHEN 4 THEN '4th'
        END
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `compliments`
--

CREATE TABLE `compliments` (
  `id` varchar(50) NOT NULL,
  `ref` varchar(20) NOT NULL,
  `date` date NOT NULL,
  `quarter` varchar(5) DEFAULT NULL,
  `client` varchar(255) NOT NULL,
  `text` text NOT NULL,
  `source` varchar(50) DEFAULT 'Facebook',
  `forwarded` varchar(255) DEFAULT NULL,
  `response` text DEFAULT NULL,
  `response_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `compliments`
--
DELIMITER $$
CREATE TRIGGER `set_compliment_quarter` BEFORE INSERT ON `compliments` FOR EACH ROW BEGIN
    SET NEW.quarter = CONCAT(
        CASE QUARTER(NEW.date)
            WHEN 1 THEN '1st'
            WHEN 2 THEN '2nd'
            WHEN 3 THEN '3rd'
            WHEN 4 THEN '4th'
        END
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `feedback_summary`
-- (See below for the actual view)
--
CREATE TABLE `feedback_summary` (
`type` varchar(10)
,`ref` varchar(20)
,`date` date
,`quarter` varchar(5)
,`client` varchar(255)
,`content` mediumtext
,`source` varchar(50)
,`assignee` varchar(255)
,`status` enum('Pending','In Progress','Resolved')
,`response` mediumtext
,`response_date` date
,`forwarded_to` varchar(255)
,`action_taken` mediumtext
,`created_at` timestamp
,`id` varchar(50)
);

-- --------------------------------------------------------

--
-- Table structure for table `sequences`
--

CREATE TABLE `sequences` (
  `type` varchar(20) NOT NULL,
  `value` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sequences`
--

INSERT INTO `sequences` (`type`, `value`, `created_at`, `updated_at`) VALUES
('complaint', 1, '2026-08-25 09:23:22', '2026-08-25 09:32:12'),
('compliment', 1, '2026-08-25 09:23:22', '2026-08-25 09:31:42');

-- --------------------------------------------------------

--
-- Structure for view `feedback_summary`
--
DROP TABLE IF EXISTS `feedback_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `feedback_summary`  AS SELECT 'Compliment' AS `type`, `compliments`.`ref` AS `ref`, `compliments`.`date` AS `date`, `compliments`.`quarter` AS `quarter`, `compliments`.`client` AS `client`, `compliments`.`text` AS `content`, `compliments`.`source` AS `source`, NULL AS `assignee`, NULL AS `status`, `compliments`.`response` AS `response`, `compliments`.`response_date` AS `response_date`, `compliments`.`forwarded` AS `forwarded_to`, NULL AS `action_taken`, `compliments`.`created_at` AS `created_at`, `compliments`.`id` AS `id` FROM `compliments`union all select 'Complaint' AS `type`,`complaints`.`ref` AS `ref`,`complaints`.`date` AS `date`,`complaints`.`quarter` AS `quarter`,`complaints`.`client` AS `client`,`complaints`.`text` AS `content`,`complaints`.`source` AS `source`,`complaints`.`assignee` AS `assignee`,`complaints`.`status` AS `status`,`complaints`.`response` AS `response`,`complaints`.`response_date` AS `response_date`,NULL AS `forwarded_to`,`complaints`.`action` AS `action_taken`,`complaints`.`created_at` AS `created_at`,`complaints`.`id` AS `id` from `complaints` order by `date` desc,`created_at` desc  ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ref` (`ref`),
  ADD KEY `idx_ref` (`ref`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_quarter` (`quarter`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_source` (`source`);

--
-- Indexes for table `compliments`
--
ALTER TABLE `compliments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ref` (`ref`),
  ADD KEY `idx_ref` (`ref`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_quarter` (`quarter`),
  ADD KEY `idx_source` (`source`);

--
-- Indexes for table `sequences`
--
ALTER TABLE `sequences`
  ADD PRIMARY KEY (`type`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
