-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: Localhost:3307
-- Generation Time: Sep 14, 2025 at 10:30 AM
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
-- Database: `hwrm`
--

-- --------------------------------------------------------

--
-- Table structure for table `hydrants`
--

CREATE TABLE `hydrants` (
  `id` int(11) NOT NULL,
  `hydrant_id` varchar(20) NOT NULL,
  `location` varchar(255) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `pressure` int(11) DEFAULT NULL,
  `flow_rate` int(11) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `last_tested` date DEFAULT NULL,
  `barangay` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hydrants`
--

INSERT INTO `hydrants` (`id`, `hydrant_id`, `location`, `latitude`, `longitude`, `pressure`, `flow_rate`, `status`, `last_tested`, `barangay`, `created_at`, `updated_at`) VALUES
(1, 'QC-H001', 'Commonwealth Ave corner Fairview Ave', 14.69650000, 121.08240000, 65, 1500, 'active', NULL, 'Commonwealth', '2025-08-31 09:39:13', '2025-08-31 09:39:13'),
(2, 'QC-H002', 'Batasan Road near City Hall', 14.70000000, 121.08000000, 70, 1600, 'active', NULL, 'Batasan Hills', '2025-08-31 09:39:13', '2025-08-31 09:39:13'),
(3, 'QC-H003', 'Payatas Road Main', 14.69300000, 121.08500000, 60, 1400, 'active', NULL, 'Payatas', '2025-08-31 09:39:13', '2025-08-31 09:39:13'),
(4, 'QC-H004', 'Holy Spirit Drive', 14.69800000, 121.07900000, 68, 1550, 'active', NULL, 'Holy Spirit', '2025-08-31 09:39:13', '2025-08-31 09:39:13'),
(5, 'QC-H005', 'Bagong Silangan Main Road', 14.70200000, 121.08700000, 62, 1450, 'inactive', NULL, 'Bagong Silangan', '2025-08-31 09:39:13', '2025-09-05 05:23:13'),
(6, 'asdasd', 'Datsun Street, Fairview, 5th District, Quezon City, Eastern Manila District, Metro Manila, 1122, Philippines', 99.99999999, 213.00000000, 21, 21, 'inactive', '2025-09-04', '123', '2025-09-05 05:22:36', '2025-09-05 05:22:49');

-- --------------------------------------------------------

--
-- Table structure for table `water_sources`
--

CREATE TABLE `water_sources` (
  `id` int(11) NOT NULL,
  `source_type` enum('hydrant','reservoir','lake','river','well','storage_tank') NOT NULL,
  `source_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `capacity` int(11) DEFAULT NULL COMMENT 'In liters',
  `pressure` int(11) DEFAULT NULL COMMENT 'In PSI',
  `flow_rate` int(11) DEFAULT NULL COMMENT 'In L/min',
  `status` enum('active','inactive','maintenance','low_flow') NOT NULL DEFAULT 'active',
  `last_inspection` date DEFAULT NULL,
  `next_inspection` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `water_sources`
--

INSERT INTO `water_sources` (`id`, `source_type`, `source_id`, `name`, `location`, `barangay`, `latitude`, `longitude`, `capacity`, `pressure`, `flow_rate`, `status`, `last_inspection`, `next_inspection`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'hydrant', '22121775', 'Solis, zaldy', '57 gold extention barranggay commonwealth quezon city', 'commonwealth', 14.69827904, 121.08117293, 500, 67, 112, 'active', '2025-09-01', NULL, 'iloveyouu', '2025-09-04 17:41:59', '2025-09-04 17:41:59'),
(2, 'hydrant', '22121772', 'Red Fire hydrant', 'mary rose strore sanchez street', 'commonwealth', 14.69741592, 121.08092963, 600, 21, 21, 'active', '2025-09-04', '2025-09-12', 'ilovyouu\nStatus changed to MAINTENANCE on 2025-09-04 20:24:15: asdasd\nStatus changed to INACTIVE on 2025-09-04 20:24:41: \nStatus changed to ACTIVE on 2025-09-04 21:57:15: asdd', '2025-09-04 17:46:04', '2025-09-04 19:57:15');

-- --------------------------------------------------------

--
-- Table structure for table `water_source_inspections`
--

CREATE TABLE `water_source_inspections` (
  `id` int(11) NOT NULL,
  `source_id` int(11) NOT NULL,
  `inspected_by` int(11) NOT NULL,
  `inspection_date` date NOT NULL,
  `pressure` int(11) DEFAULT NULL,
  `flow_rate` int(11) DEFAULT NULL,
  `condition` enum('excellent','good','fair','poor','critical') NOT NULL,
  `issues_found` text DEFAULT NULL,
  `actions_taken` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `next_inspection` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `water_source_inspections`
--

INSERT INTO `water_source_inspections` (`id`, `source_id`, `inspected_by`, `inspection_date`, `pressure`, `flow_rate`, `condition`, `issues_found`, `actions_taken`, `recommendations`, `next_inspection`, `created_at`) VALUES
(1, 2, 1, '2025-09-04', 21, 21, 'good', 'asda', 'asdasd', 'asdasd', '2025-09-12', '2025-09-04 19:21:22');

-- --------------------------------------------------------

--
-- Table structure for table `water_source_maintenance`
--

CREATE TABLE `water_source_maintenance` (
  `id` int(11) NOT NULL,
  `source_id` int(11) NOT NULL,
  `maintenance_type` varchar(50) NOT NULL,
  `performed_by` int(11) NOT NULL,
  `maintenance_date` date NOT NULL,
  `description` text NOT NULL,
  `parts_used` text DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `hours_spent` decimal(4,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `water_source_maintenance`
--

INSERT INTO `water_source_maintenance` (`id`, `source_id`, `maintenance_type`, `performed_by`, `maintenance_date`, `description`, `parts_used`, `cost`, `hours_spent`, `created_at`) VALUES
(1, 2, 'asdasd', 1, '2025-09-04', 'asdasd', 'asdasd', 21.00, 2.00, '2025-09-04 19:10:48');

-- --------------------------------------------------------

--
-- Table structure for table `water_source_status_log`
--

CREATE TABLE `water_source_status_log` (
  `id` int(11) NOT NULL,
  `source_id` int(11) NOT NULL,
  `old_status` enum('active','inactive','maintenance','low_flow') NOT NULL,
  `new_status` enum('active','inactive','maintenance','low_flow') NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `water_source_status_log`
--

INSERT INTO `water_source_status_log` (`id`, `source_id`, `old_status`, `new_status`, `changed_by`, `changed_at`, `notes`) VALUES
(1, 2, 'maintenance', 'maintenance', 1, '2025-09-04 18:24:15', 'asdasd'),
(2, 2, 'inactive', 'inactive', 1, '2025-09-04 18:24:41', ''),
(3, 2, 'active', 'active', 1, '2025-09-04 19:57:15', 'asdd');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `hydrants`
--
ALTER TABLE `hydrants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hydrant_id` (`hydrant_id`),
  ADD KEY `barangay` (`barangay`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `water_sources`
--
ALTER TABLE `water_sources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `source_id` (`source_id`),
  ADD KEY `source_type` (`source_type`),
  ADD KEY `barangay` (`barangay`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `water_source_inspections`
--
ALTER TABLE `water_source_inspections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `water_source_maintenance`
--
ALTER TABLE `water_source_maintenance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `water_source_status_log`
--
ALTER TABLE `water_source_status_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `source_id` (`source_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `hydrants`
--
ALTER TABLE `hydrants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `water_sources`
--
ALTER TABLE `water_sources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `water_source_inspections`
--
ALTER TABLE `water_source_inspections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `water_source_maintenance`
--
ALTER TABLE `water_source_maintenance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `water_source_status_log`
--
ALTER TABLE `water_source_status_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `water_source_inspections`
--
ALTER TABLE `water_source_inspections`
  ADD CONSTRAINT `water_source_inspections_ibfk_1` FOREIGN KEY (`source_id`) REFERENCES `water_sources` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `water_source_maintenance`
--
ALTER TABLE `water_source_maintenance`
  ADD CONSTRAINT `water_source_maintenance_ibfk_1` FOREIGN KEY (`source_id`) REFERENCES `water_sources` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `water_source_status_log`
--
ALTER TABLE `water_source_status_log`
  ADD CONSTRAINT `water_source_status_log_ibfk_1` FOREIGN KEY (`source_id`) REFERENCES `water_sources` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
