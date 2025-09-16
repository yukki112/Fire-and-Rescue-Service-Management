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
-- Database: `piar`
--

-- --------------------------------------------------------

--
-- Table structure for table `cause_origin_investigation`
--

CREATE TABLE `cause_origin_investigation` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `investigator_id` int(11) NOT NULL,
  `investigation_date` date NOT NULL,
  `cause_classification` varchar(100) DEFAULT NULL,
  `origin_location` varchar(255) DEFAULT NULL,
  `ignition_source` varchar(100) DEFAULT NULL,
  `contributing_factors` text DEFAULT NULL,
  `evidence_collected` text DEFAULT NULL,
  `witness_statements` text DEFAULT NULL,
  `investigation_status` enum('ongoing','completed','closed') NOT NULL DEFAULT 'ongoing',
  `final_report` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `damage_assessment`
--

CREATE TABLE `damage_assessment` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `assessor_id` int(11) NOT NULL,
  `assessment_date` date NOT NULL,
  `property_damage` decimal(12,2) DEFAULT NULL,
  `content_damage` decimal(12,2) DEFAULT NULL,
  `business_interruption` decimal(12,2) DEFAULT NULL,
  `total_estimated_loss` decimal(12,2) DEFAULT NULL,
  `affected_structures` int(11) DEFAULT NULL,
  `displaced_persons` int(11) DEFAULT NULL,
  `casualties` int(11) DEFAULT NULL,
  `fatalities` int(11) DEFAULT NULL,
  `environmental_impact` text DEFAULT NULL,
  `assessment_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incident_actions`
--

CREATE TABLE `incident_actions` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `performed_by` int(11) NOT NULL,
  `performed_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incident_analysis_reports`
--

CREATE TABLE `incident_analysis_reports` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `report_title` varchar(255) NOT NULL,
  `incident_summary` text NOT NULL,
  `response_timeline` text NOT NULL,
  `personnel_involved` text NOT NULL,
  `units_involved` text NOT NULL,
  `cause_investigation` text NOT NULL,
  `origin_investigation` text NOT NULL,
  `damage_assessment` text NOT NULL,
  `lessons_learned` text NOT NULL,
  `recommendations` text NOT NULL,
  `status` enum('draft','submitted','approved','archived') NOT NULL DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `incident_analysis_reports`
--

INSERT INTO `incident_analysis_reports` (`id`, `incident_id`, `report_title`, `incident_summary`, `response_timeline`, `personnel_involved`, `units_involved`, `cause_investigation`, `origin_investigation`, `damage_assessment`, `lessons_learned`, `recommendations`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 0, 'asdas', 'dasdasd', 'asdas', 'dasdasd', 'asdasd', 'asd', 'adasd', 'asdasd', 'asdasd', 'asdasd', 'submitted', 1, '2025-09-09 11:20:14', '2025-09-09 11:20:18');

-- --------------------------------------------------------

--
-- Table structure for table `lessons_learned`
--

CREATE TABLE `lessons_learned` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `lesson_description` text NOT NULL,
  `recommendation` text NOT NULL,
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `responsible_department` varchar(100) DEFAULT NULL,
  `implementation_status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `target_completion_date` date DEFAULT NULL,
  `actual_completion_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personnel_involvement`
--

CREATE TABLE `personnel_involvement` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `role` varchar(100) NOT NULL,
  `arrival_time` datetime DEFAULT NULL,
  `departure_time` datetime DEFAULT NULL,
  `actions_performed` text DEFAULT NULL,
  `performance_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_archives`
--

CREATE TABLE `report_archives` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `archive_date` date NOT NULL,
  `archived_by` int(11) NOT NULL,
  `archive_reason` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `response_timeline`
--

CREATE TABLE `response_timeline` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `event_description` text NOT NULL,
  `event_time` datetime NOT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `personnel_id` int(11) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `response_timeline`
--

INSERT INTO `response_timeline` (`id`, `incident_id`, `event_type`, `event_description`, `event_time`, `unit_id`, `personnel_id`, `location`, `notes`, `created_at`) VALUES
(1, 1, 'completion', 'asd', '2025-09-09 11:32:00', 0, 123, 'Datsun Street, Fairview, 5th District, Quezon City, Eastern Manila District, Metro Manila, 1122, Philippines', 'asd', '2025-09-09 11:32:59');

-- --------------------------------------------------------

--
-- Table structure for table `unit_involvement`
--

CREATE TABLE `unit_involvement` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `dispatch_time` datetime NOT NULL,
  `arrival_time` datetime DEFAULT NULL,
  `departure_time` datetime DEFAULT NULL,
  `equipment_used` text DEFAULT NULL,
  `actions_performed` text DEFAULT NULL,
  `performance_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cause_origin_investigation`
--
ALTER TABLE `cause_origin_investigation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `investigator_id` (`investigator_id`),
  ADD KEY `investigation_status` (`investigation_status`);

--
-- Indexes for table `damage_assessment`
--
ALTER TABLE `damage_assessment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `assessor_id` (`assessor_id`);

--
-- Indexes for table `incident_actions`
--
ALTER TABLE `incident_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `action_type` (`action_type`);

--
-- Indexes for table `incident_analysis_reports`
--
ALTER TABLE `incident_analysis_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `lessons_learned`
--
ALTER TABLE `lessons_learned`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `category` (`category`),
  ADD KEY `priority` (`priority`),
  ADD KEY `implementation_status` (`implementation_status`);

--
-- Indexes for table `personnel_involvement`
--
ALTER TABLE `personnel_involvement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `personnel_id` (`personnel_id`);

--
-- Indexes for table `report_archives`
--
ALTER TABLE `report_archives`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `response_timeline`
--
ALTER TABLE `response_timeline`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `event_type` (`event_type`),
  ADD KEY `event_time` (`event_time`);

--
-- Indexes for table `unit_involvement`
--
ALTER TABLE `unit_involvement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cause_origin_investigation`
--
ALTER TABLE `cause_origin_investigation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `damage_assessment`
--
ALTER TABLE `damage_assessment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incident_actions`
--
ALTER TABLE `incident_actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incident_analysis_reports`
--
ALTER TABLE `incident_analysis_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `lessons_learned`
--
ALTER TABLE `lessons_learned`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personnel_involvement`
--
ALTER TABLE `personnel_involvement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_archives`
--
ALTER TABLE `report_archives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `response_timeline`
--
ALTER TABLE `response_timeline`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `unit_involvement`
--
ALTER TABLE `unit_involvement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `report_archives`
--
ALTER TABLE `report_archives`
  ADD CONSTRAINT `report_archives_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `incident_analysis_reports` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
