SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `records_srb2rec`
--
CREATE DATABASE IF NOT EXISTS `records_srb2rec` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `records_srb2rec`;

-- --------------------------------------------------------

--
-- Table structure for table `characters`
--

CREATE TABLE `characters` (
  `character_id` bigint(20) NOT NULL,
  `name` varchar(32) CHARACTER SET latin1 NOT NULL,
  `face_icon` varchar(32) CHARACTER SET latin1 NOT NULL,
  `checksum` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `class_id` bigint(20) NOT NULL,
  `map_id` bigint(20) NOT NULL,
  `character_id` bigint(20) NOT NULL,
  `type` varchar(24) NOT NULL,
  `extra-rules` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `manual_submissions`
--

CREATE TABLE `manual_submissions` (
  `manual_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `class_id` bigint(20) NOT NULL,
  `submit_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `version` varchar(7) NOT NULL DEFAULT '000.000',
  `type` varchar(32) NOT NULL,
  `url` varchar(256) NOT NULL,
  `record` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `maps`
--

CREATE TABLE `maps` (
  `map_id` bigint(20) NOT NULL,
  `pack_id` bigint(20) NOT NULL,
  `map_number` int(11) NOT NULL,
  `zone_name` varchar(27) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `is_zone` tinyint(1) NOT NULL DEFAULT '1',
  `act` int(11) NOT NULL DEFAULT '0',
  `checksum` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `auto_submit` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `packs`
--

CREATE TABLE `packs` (
  `pack_id` bigint(20) NOT NULL,
  `name` varchar(96) NOT NULL,
  `hidden` tinyint(1) NOT NULL DEFAULT '0',
  `full_display` tinyint(1) NOT NULL DEFAULT '0',
  `page_arrangement` text,
  `images_folder` varchar(32) DEFAULT NULL,
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `records`
--

CREATE TABLE `records` (
  `record_id` bigint(20) NOT NULL,
  `class_id` bigint(20) NOT NULL,
  `version` varchar(7) NOT NULL DEFAULT '000.000',
  `record` int(11) NOT NULL DEFAULT '0',
  `record_time` int(11) NOT NULL DEFAULT '-1',
  `user_id` bigint(20) NOT NULL,
  `submit_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `replay_id` bigint(20) NOT NULL DEFAULT '-1',
  `manual_id` bigint(20) NOT NULL DEFAULT '-1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `replay_data`
--

CREATE TABLE `replay_data` (
  `replay_id` bigint(20) NOT NULL,
  `checksum_md5` varchar(32) NOT NULL,
  `checksum_sha1` varchar(40) NOT NULL,
  `gzipped` tinyint(1) NOT NULL DEFAULT '0',
  `data` mediumblob
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `replay_info`
--

CREATE TABLE `replay_info` (
  `replay_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `map_id` bigint(20) NOT NULL,
  `character_id` bigint(20) NOT NULL,
  `submit_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `version` varchar(7) NOT NULL,
  `player_name` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `score` int(11) NOT NULL DEFAULT '-1',
  `time` int(11) NOT NULL DEFAULT '-1',
  `rings` int(11) NOT NULL DEFAULT '-1',
  `nscore` int(11) NOT NULL DEFAULT '-1',
  `ntime` int(11) NOT NULL DEFAULT '-1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` bigint(20) NOT NULL,
  `record_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `data` text NOT NULL,
  `result` smallint(2) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `site_data`
--

CREATE TABLE `site_data` (
  `data_key` varchar(64) NOT NULL,
  `value` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` bigint(20) NOT NULL,
  `mb_user` int(11) NOT NULL,
  `name` varchar(128) NOT NULL DEFAULT '',
  `avatar_url` varchar(256) DEFAULT NULL,
  `admin` tinyint(1) NOT NULL DEFAULT '0',
  `verify_hash` varchar(128) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `characters`
--
ALTER TABLE `characters`
  ADD PRIMARY KEY (`character_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`class_id`),
  ADD UNIQUE KEY `unique_class` (`map_id`,`character_id`,`type`),
  ADD KEY `map_id` (`map_id`);

--
-- Indexes for table `manual_submissions`
--
ALTER TABLE `manual_submissions`
  ADD PRIMARY KEY (`manual_id`);

--
-- Indexes for table `maps`
--
ALTER TABLE `maps`
  ADD PRIMARY KEY (`map_id`),
  ADD UNIQUE KEY `checksum` (`checksum`),
  ADD KEY `pack_id` (`pack_id`);

--
-- Indexes for table `packs`
--
ALTER TABLE `packs`
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `records`
--
ALTER TABLE `records`
  ADD PRIMARY KEY (`record_id`),
  ADD UNIQUE KEY `record_is_unique` (`class_id`,`user_id`,`version`);

--
-- Indexes for table `replay_data`
--
ALTER TABLE `replay_data`
  ADD PRIMARY KEY (`replay_id`),
  ADD UNIQUE KEY `checksum_values` (`checksum_md5`,`checksum_sha1`);

--
-- Indexes for table `replay_info`
--
ALTER TABLE `replay_info`
  ADD PRIMARY KEY (`replay_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD UNIQUE KEY `record_id` (`record_id`),
  ADD KEY `auto` (`report_id`);

--
-- Indexes for table `site_data`
--
ALTER TABLE `site_data`
  ADD PRIMARY KEY (`data_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `characters`
--
ALTER TABLE `characters`
  MODIFY `character_id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `class_id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `manual_submissions`
--
ALTER TABLE `manual_submissions`
  MODIFY `manual_id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `maps`
--
ALTER TABLE `maps`
  MODIFY `map_id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `records`
--
ALTER TABLE `records`
  MODIFY `record_id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `replay_info`
--
ALTER TABLE `replay_info`
  MODIFY `replay_id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` bigint(20) NOT NULL AUTO_INCREMENT;COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
