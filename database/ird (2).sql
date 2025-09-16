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
-- Database: `ird`
--

-- --------------------------------------------------------

--
-- Table structure for table `communications`
--

CREATE TABLE `communications` (
  `id` int(11) NOT NULL,
  `channel` varchar(50) NOT NULL,
  `sender` varchar(100) NOT NULL,
  `receiver` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `incident_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `communications`
--

INSERT INTO `communications` (`id`, `channel`, `sender`, `receiver`, `message`, `incident_id`, `created_at`) VALUES
(1, 'email', 'Stephen Viray', 'asd', 'sdqwdqwe', 4, '2025-08-31 14:43:21'),
(2, 'alert', 'Stephen Viray', 'here you go', 'no please dont', 5, '2025-08-31 18:55:03'),
(3, 'sms', 'Stephen Viray', 'here you go po', 'asd', 9, '2025-09-03 02:29:39'),
(4, 'email', 'Stephen Viray', 'stephen kyle viray', 'bilis po baby', 11, '2025-09-04 09:12:34');

-- --------------------------------------------------------

--
-- Table structure for table `dispatches`
--

CREATE TABLE `dispatches` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `dispatched_at` datetime NOT NULL,
  `arrived_at` datetime DEFAULT NULL,
  `status` enum('dispatched','responding','onscene','completed') NOT NULL DEFAULT 'dispatched',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dispatches`
--

INSERT INTO `dispatches` (`id`, `incident_id`, `unit_id`, `dispatched_at`, `arrived_at`, `status`, `created_at`, `updated_at`) VALUES
(4, 5, 5, '2025-09-01 00:20:07', NULL, 'completed', '2025-08-31 16:20:07', '2025-09-07 16:58:54'),
(5, 5, 5, '2025-09-01 00:20:08', NULL, 'completed', '2025-08-31 16:20:08', '2025-09-04 09:06:02'),
(6, 10, 1, '2025-09-04 16:57:40', NULL, 'completed', '2025-09-04 08:57:40', '2025-09-04 08:58:08'),
(7, 5, 3, '2025-09-04 16:57:51', NULL, 'completed', '2025-09-04 08:57:51', '2025-09-04 08:58:05'),
(8, 5, 1, '2025-09-04 16:58:17', NULL, 'completed', '2025-09-04 08:58:17', '2025-09-04 09:06:01'),
(9, 11, 3, '2025-09-04 17:05:14', NULL, 'completed', '2025-09-04 09:05:14', '2025-09-04 09:05:59'),
(10, 11, 1, '2025-09-04 17:28:27', NULL, 'completed', '2025-09-04 09:28:27', '2025-09-07 16:58:52'),
(11, 11, 5, '2025-09-04 17:28:34', NULL, 'completed', '2025-09-04 09:28:34', '2025-09-07 16:58:50'),
(12, 14, 3, '2025-09-08 00:43:30', NULL, 'completed', '2025-09-07 16:43:30', '2025-09-07 16:58:48'),
(13, 17, 5, '2025-09-08 01:22:57', NULL, 'completed', '2025-09-07 17:22:57', '2025-09-07 17:23:11'),
(14, 20, 1, '2025-09-13 16:05:20', NULL, 'completed', '2025-09-13 08:05:20', '2025-09-13 09:45:51'),
(15, 21, 5, '2025-09-13 17:45:32', NULL, 'completed', '2025-09-13 09:45:32', '2025-09-13 09:45:50'),
(16, 21, 3, '2025-09-13 17:45:43', NULL, 'completed', '2025-09-13 09:45:43', '2025-09-13 09:45:48'),
(17, 19, 1, '2025-09-13 17:46:07', NULL, 'completed', '2025-09-13 09:46:07', '2025-09-13 09:46:15'),
(18, 19, 3, '2025-09-13 17:46:07', NULL, 'completed', '2025-09-13 09:46:07', '2025-09-13 09:46:17'),
(19, 3, 3, '2025-09-13 17:46:34', NULL, 'completed', '2025-09-13 09:46:34', '2025-09-13 09:46:38'),
(20, 13, 5, '2025-09-13 17:46:49', NULL, 'completed', '2025-09-13 09:46:49', '2025-09-13 09:46:55'),
(21, 13, 1, '2025-09-13 17:46:49', NULL, 'completed', '2025-09-13 09:46:49', '2025-09-13 09:46:54'),
(22, 12, 5, '2025-09-13 17:47:08', NULL, 'completed', '2025-09-13 09:47:08', '2025-09-13 09:47:34'),
(23, 12, 1, '2025-09-13 17:47:08', NULL, 'completed', '2025-09-13 09:47:08', '2025-09-13 09:47:31'),
(24, 18, 3, '2025-09-13 19:19:23', NULL, 'completed', '2025-09-13 11:19:23', '2025-09-13 11:19:45'),
(25, 18, 8, '2025-09-13 19:19:23', NULL, 'completed', '2025-09-13 11:19:23', '2025-09-13 11:19:47'),
(26, 18, 13, '2025-09-13 19:19:23', NULL, 'completed', '2025-09-13 11:19:23', '2025-09-13 11:19:48'),
(27, 18, 19, '2025-09-13 19:19:23', NULL, 'completed', '2025-09-13 11:19:23', '2025-09-13 11:19:49'),
(28, 18, 25, '2025-09-13 19:19:23', NULL, 'completed', '2025-09-13 11:19:23', '2025-09-13 11:19:50'),
(29, 16, 23, '2025-09-13 21:06:48', NULL, 'completed', '2025-09-13 13:06:48', '2025-09-13 13:07:20'),
(30, 16, 15, '2025-09-13 21:07:03', NULL, 'completed', '2025-09-13 13:07:03', '2025-09-13 13:07:19'),
(31, 16, 11, '2025-09-13 21:07:08', NULL, 'completed', '2025-09-13 13:07:08', '2025-09-13 13:07:18');

-- --------------------------------------------------------

--
-- Table structure for table `hospitals`
--

CREATE TABLE `hospitals` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `specialties` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `barangay` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hospitals`
--

INSERT INTO `hospitals` (`id`, `name`, `address`, `latitude`, `longitude`, `phone`, `emergency_contact`, `capacity`, `specialties`, `status`, `barangay`, `created_at`, `updated_at`) VALUES
(1, 'Quezon City General Hospital', 'Seminary Rd, Novaliches', 14.72340000, 121.05220000, '(02) 8806-3737', '(02) 8806-3700', 500, 'Emergency Medicine, Trauma, Burns', 'active', 'Novaliches', '2025-08-31 09:38:33', '2025-08-31 09:38:33'),
(2, 'East Avenue Medical Center', 'East Ave, Diliman', 14.65070000, 121.04940000, '(02) 8928-0611', '(02) 8928-0600', 650, 'Emergency Medicine, Cardiology, Neurology', 'active', 'Diliman', '2025-08-31 09:38:33', '2025-08-31 09:38:33');

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `id` int(11) NOT NULL,
  `incident_type` varchar(50) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `incident_date` date NOT NULL,
  `incident_time` time NOT NULL,
  `description` text NOT NULL,
  `injuries` int(11) DEFAULT 0,
  `fatalities` int(11) DEFAULT 0,
  `people_trapped` int(11) DEFAULT 0,
  `hazardous_materials` tinyint(1) DEFAULT 0,
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('pending','dispatched','responding','resolved') NOT NULL DEFAULT 'pending',
  `reported_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `incidents`
--

INSERT INTO `incidents` (`id`, `incident_type`, `barangay`, `location`, `latitude`, `longitude`, `incident_date`, `incident_time`, `description`, `injuries`, `fatalities`, `people_trapped`, `hazardous_materials`, `priority`, `status`, `reported_by`, `created_at`, `updated_at`) VALUES
(1, 'structure-fire', 'Commonwealth', '123 Commonwealth Ave', 14.69650000, 121.08240000, '2025-08-21', '10:15:00', 'Commercial building fire on 3rd floor', 0, 0, 0, 0, 'critical', 'responding', NULL, '2025-08-31 09:38:33', '2025-08-31 09:38:33'),
(2, 'vehicle-fire', 'Payatas', 'Payatas Road', 14.69300000, 121.08500000, '2025-08-21', '09:50:00', 'Vehicle fire spreading to nearby structures', 0, 0, 0, 0, 'high', 'responding', NULL, '2025-08-31 09:38:33', '2025-08-31 09:38:33'),
(3, 'rescue', 'Batasan Hills', '45 Batasan Road', 14.70000000, 121.08000000, '2025-08-21', '09:30:00', 'Person trapped in collapsed structure', 0, 0, 0, 0, 'high', 'resolved', NULL, '2025-08-31 09:38:33', '2025-09-13 09:46:38'),
(4, 'Medical Emergency', 'Commonwealth', 'Datsun Street, Fairview, 5th District, Quezon City, Eastern Manila District, Metro Manila, 1122, Philippines', 99.99999999, 999.99999999, '2025-08-31', '19:33:06', 'asdasdasd', 0, 0, 0, 0, 'high', 'dispatched', 5, '2025-08-31 11:33:06', '2025-08-31 14:41:24'),
(5, 'Fire', 'Alicia', '54 gold ext', 0.00000000, 0.00000000, '2025-08-31', '22:42:39', 'asdasdasd', 0, 0, 0, 0, 'high', 'resolved', 1, '2025-08-31 14:42:39', '2025-09-07 16:58:54'),
(6, 'Medical Emergency', 'Bagong Silangan', '123 Main Street', 14.72340000, 121.05220000, '2025-09-02', '14:30:00', 'Elderly person experiencing chest pain', 1, 0, 0, 0, 'high', 'responding', 1, '2025-09-02 16:51:45', '2025-09-02 16:51:45'),
(7, 'Traffic Accident', 'Commonwealth', 'Commonwealth Ave near Litex', 14.72340000, 121.05220000, '2025-09-02', '15:45:00', 'Two vehicles collision with injuries', 2, 0, 2, 0, 'critical', 'dispatched', 1, '2025-09-02 16:51:45', '2025-09-02 16:51:45'),
(8, 'Fire', 'Batasan Hills', '45 Batasan Road', 14.70000000, 121.08000000, '2025-09-02', '16:20:00', 'Residential fire with people trapped', 0, 0, 3, 0, 'critical', 'responding', 1, '2025-09-02 16:51:45', '2025-09-02 16:51:45'),
(9, 'other', 'Commonwealth', '57 sanchez street ', NULL, NULL, '0000-00-00', '00:00:00', 'gas leaked', 0, 0, 0, 0, 'low', 'responding', 1, '2025-09-03 02:28:37', '2025-09-03 02:30:01'),
(10, 'Fire', 'Commonwealth', 'Datsun Street, Fairview, 5th District, Quezon City, Eastern Manila District, Metro Manila, 1122, Philippines', NULL, NULL, '2025-09-04', '14:15:22', 'asdadad', 2, 3, 1, 0, 'high', 'resolved', 1, '2025-09-04 06:15:22', '2025-09-04 08:58:08'),
(11, 'Hazardous Materials', 'Commonwealth', 'mary rose strore sanchez street', NULL, NULL, '2025-09-04', '17:04:07', 'alak', 0, 0, 0, 1, 'low', 'resolved', 1, '2025-09-04 09:04:07', '2025-09-07 16:58:52'),
(12, 'Hazardous Materials', 'Bagong Silangan', '57 gold extention barranggay commonwealth quezon city', NULL, NULL, '2025-09-08', '00:22:14', 'asdasd', 1, 3, 0, 1, 'critical', 'resolved', 1, '2025-09-07 16:22:14', '2025-09-13 09:47:34'),
(13, 'Hazardous Materials', 'Bagong Silangan', 'Datsun Street, Fairview, 5th District, Quezon City, Eastern Manila District, Metro Manila, 1122, Philippines', NULL, NULL, '2025-09-08', '00:42:04', 'asdasdsa', 1, 3, 0, 1, 'high', 'resolved', 1, '2025-09-07 16:42:04', '2025-09-13 09:46:55'),
(14, 'Hazardous Materials', 'Batasan Hills', '57 gold extention barranggay commonwealth quezon city', NULL, NULL, '2025-09-08', '00:43:13', 'ayoko na po', 5, 3, 0, 0, 'critical', 'resolved', 1, '2025-09-07 16:43:13', '2025-09-07 16:58:48'),
(15, 'structure-fire', 'Bagong Silangan', 'Datsun Street, Fairview, 5th District, Quezon City, Eastern Manila District, Metro Manila, 1122, Philippines', NULL, NULL, '2025-09-08', '00:44:43', 'asdadasd', 36, 120, 500, 0, 'low', 'pending', 1, '2025-09-07 16:44:43', '2025-09-07 16:44:43'),
(16, 'vehicle-fire', 'Commonwealth', 'mary rose strore sanchez street', NULL, NULL, '2025-09-08', '01:01:01', 'may nasusunog na kotse at may 3 tao sa loob', 3, 3, 3, 0, 'high', 'resolved', 1, '2025-09-07 17:01:01', '2025-09-13 13:07:20'),
(17, 'Medical Emergency', 'Commonwealth', '57 gold extention barranggay commonwealth quezon city', NULL, NULL, '2025-09-08', '01:22:36', 'wawa', 3, 1, 2, 0, 'high', 'resolved', 1, '2025-09-07 17:22:36', '2025-09-07 17:23:11'),
(18, 'Medical Emergency', 'Batasan Hills', '57 gold extention barranggay commonwealth quezon city', NULL, NULL, '2025-09-08', '01:26:44', 'asdasd', 3, 5, 7, 0, 'high', 'resolved', 1, '2025-09-07 17:26:44', '2025-09-13 11:19:50'),
(19, 'structure-fire', 'Batasan Hills', '57 gold extention barranggay commonwealth quezon city', NULL, NULL, '2025-09-09', '22:52:48', 'asdasdas', 1, 2, 3, 0, 'low', 'resolved', 1, '2025-09-09 14:52:48', '2025-09-13 09:46:17'),
(20, 'structure-fire', 'Commonwealth', 'mary rose strore sanchez street', NULL, NULL, '2025-09-13', '00:53:46', 'no po', 2, 1, 0, 0, 'medium', 'resolved', 1, '2025-09-12 16:53:46', '2025-09-13 09:45:51'),
(21, 'structure-fire', 'Commonwealth', 'mary rose strore sanchez street', NULL, NULL, '2025-09-13', '00:54:24', 'no po', 2, 1, 0, 0, 'medium', 'resolved', 1, '2025-09-12 16:54:24', '2025-09-13 09:45:50');

-- --------------------------------------------------------

--
-- Table structure for table `incident_logs`
--

CREATE TABLE `incident_logs` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `incident_logs`
--

INSERT INTO `incident_logs` (`id`, `incident_id`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, 1, 1, 'Incident Created', 'Commercial building fire reported', '2025-09-02 16:51:45'),
(2, 1, 1, 'Status Updated', 'Status changed to responding', '2025-09-02 16:51:45'),
(3, 2, 1, 'Incident Created', 'Vehicle fire reported', '2025-09-02 16:51:45'),
(4, 3, 1, 'Incident Created', 'Rescue operation requested', '2025-09-02 16:51:45'),
(5, 9, 1, 'Incident Created', 'gas leaked', '2025-09-03 02:28:37'),
(6, 9, 1, 'Status Updated', 'Status changed to responding', '2025-09-03 02:30:01'),
(7, 10, 1, 'Incident Created', 'asdadad', '2025-09-04 06:15:22'),
(8, 10, 1, 'Unit Assigned', 'Unit ID: 1 assigned to incident', '2025-09-04 08:57:40'),
(9, 5, 1, 'Unit Assigned', 'Unit ID: 3 assigned to incident', '2025-09-04 08:57:51'),
(10, 5, 1, 'Unit Assigned', 'Unit ID: 1 assigned to incident', '2025-09-04 08:58:17'),
(11, 11, 1, 'Incident Created', 'alak', '2025-09-04 09:04:07'),
(12, 11, 1, 'Unit Assigned', 'Unit ID: 3 assigned to incident', '2025-09-04 09:05:14'),
(13, 11, 1, 'Unit Assigned', 'Unit 1 assigned to incident', '2025-09-04 09:28:28'),
(14, 11, 1, 'Unit Assigned', 'Unit 5 assigned to incident', '2025-09-04 09:28:34'),
(15, 11, 1, 'Status Updated', 'Status changed to resolved', '2025-09-04 09:28:41'),
(16, 11, 1, 'Status Updated', 'Status changed to dispatched', '2025-09-04 09:28:44'),
(17, 11, 1, 'Status Updated', 'Status changed to pending', '2025-09-04 09:28:46'),
(18, 11, 1, 'Status Updated', 'Status changed to responding', '2025-09-04 09:28:48'),
(19, 12, 1, 'Incident Created', 'asdasd', '2025-09-07 16:22:14'),
(20, 12, 1, 'AI Recommendations', '{\"units\":[{\"unit_id\":3,\"unit_name\":\"Medic 3\",\"unit_type\":\"Ambulance\",\"suitability_score\":8,\"specialization_match\":0.3}],\"hospitals\":[{\"id\":2,\"name\":\"East Avenue Medical Center\",\"address\":\"East Ave, Diliman\",\"latitude\":\"14.65070000\",\"longitude\":\"121.04940000\",\"phone\":\"(02) 8928-0611\",\"emergency_contact\":\"(02) 8928-0600\",\"capacity\":650,\"specialties\":\"Emergency Medicine, Cardiology, Neurology\",\"status\":\"active\",\"barangay\":\"Diliman\",\"created_at\":\"2025-08-31 17:38:33\",\"updated_at\":\"2025-08-31 17:38:33\"},{\"id\":1,\"name\":\"Quezon City General Hospital\",\"address\":\"Seminary Rd, Novaliches\",\"latitude\":\"14.72340000\",\"longitude\":\"121.05220000\",\"phone\":\"(02) 8806-3737\",\"emergency_contact\":\"(02) 8806-3700\",\"capacity\":500,\"specialties\":\"Emergency Medicine, Trauma, Burns\",\"status\":\"active\",\"barangay\":\"Novaliches\",\"created_at\":\"2025-08-31 17:38:33\",\"updated_at\":\"2025-08-31 17:38:33\"}],\"priority_adjustment\":\"AI recommends changing priority from high to critical\",\"estimated_response_time\":15,\"risk_assessment\":\"Critical situation. Maximum resource allocation required.\"}', '2025-09-07 16:22:14'),
(21, 13, 1, 'Incident Created', 'asdasdsa', '2025-09-07 16:42:04'),
(22, 14, 1, 'Incident Created', 'ayoko na po', '2025-09-07 16:43:13'),
(23, 14, 1, 'Unit Assigned', 'Unit ID: 3 assigned to incident', '2025-09-07 16:43:30'),
(24, 15, 1, 'Incident Created', 'asdadasd', '2025-09-07 16:44:43'),
(25, 16, 1, 'AI Recommendations', '{\"recommended_units\":[\"Ambulance 1\",\"Command Unit 1\"],\"estimated_response_time\":5,\"risk_level\":\"critical\",\"notes\":\"CRITICAL INCIDENT: Maximum response required. Alert all available units. Fatalities confirmed. Prepare for coroner notification and scene preservation.\",\"analysis_timestamp\":\"2025-09-07 19:01:02\"}', '2025-09-07 17:01:02'),
(26, 16, 1, 'Incident Created', 'may nasusunog na kotse at may 3 tao sa loob', '2025-09-07 17:01:02'),
(27, 17, 1, 'Incident Created', 'wawa', '2025-09-07 17:22:36'),
(28, 17, 1, 'Unit Assigned', 'Unit ID: 5 assigned to incident', '2025-09-07 17:22:57'),
(29, 18, 1, 'Incident Created', 'asdasd', '2025-09-07 17:26:44'),
(30, 19, 1, 'Incident Created', 'asdasdas', '2025-09-09 14:52:48'),
(31, 20, 1, 'Incident Created', 'no po', '2025-09-12 16:53:46'),
(32, 21, 1, 'Incident Created', 'no po', '2025-09-12 16:54:24'),
(33, 20, 1, 'Unit Assigned', 'Unit ID: 1 assigned to incident', '2025-09-13 08:05:20'),
(34, 21, 1, 'Unit Assigned', 'Unit ID: 5 assigned to incident', '2025-09-13 09:45:32'),
(35, 21, 1, 'AI Units Assigned', 'AI assigned 1 units to incident', '2025-09-13 09:45:43'),
(36, 19, 1, 'AI Units Assigned', 'AI assigned 2 units to incident', '2025-09-13 09:46:07'),
(37, 3, 1, 'AI Units Assigned', 'AI assigned 1 units to incident', '2025-09-13 09:46:34'),
(38, 13, 1, 'AI Units Assigned', 'AI assigned 2 units to incident', '2025-09-13 09:46:49'),
(39, 12, 1, 'AI Units Assigned', 'AI assigned 2 units to incident', '2025-09-13 09:47:08'),
(40, 18, 1, 'AI Units Assigned', 'AI assigned 5 units to incident based on proximity and type matching', '2025-09-13 11:19:23'),
(41, 16, 1, 'Unit Assigned', 'Unit ID: 23 assigned to incident', '2025-09-13 13:06:48'),
(42, 16, 1, 'Unit Assigned', 'Unit ID: 15 assigned to incident', '2025-09-13 13:07:03'),
(43, 16, 1, 'Unit Assigned', 'Unit ID: 11 assigned to incident', '2025-09-13 13:07:08');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `barangay_filter` varchar(100) DEFAULT NULL,
  `incident_type_filter` varchar(50) DEFAULT NULL,
  `format` varchar(10) NOT NULL DEFAULT 'pdf',
  `generated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `report_type`, `title`, `description`, `start_date`, `end_date`, `barangay_filter`, `incident_type_filter`, `format`, `generated_by`, `created_at`) VALUES
(1, 'incident_summary', 'asd', 'sadasd', '2025-08-31', '2025-09-05', 'Bagong Silangan', 'Traffic Accident', 'excel', 5, '2025-08-31 11:30:25'),
(2, 'incident_summary', 'asd', 'sadasd', '2025-08-31', '2025-09-05', 'Bagong Silangan', 'Traffic Accident', 'excel', 5, '2025-08-31 11:30:29'),
(3, 'resource_report', 'please please please', '12', '2025-09-11', '2025-09-25', NULL, NULL, 'excel', 1, '2025-09-03 02:30:28'),
(4, 'resource_utilization', 'wag pooo', 'asdasdasdasd', '2025-08-04', '2025-09-04', 'Bagong Silangan', 'Hazardous Materials', 'excel', 1, '2025-09-04 09:46:12'),
(5, 'resource_utilization', 'wag pooo', 'asdasdasdasd', '2025-08-04', '2025-09-04', 'Bagong Silangan', 'Hazardous Materials', 'excel', 1, '2025-09-04 09:46:17'),
(6, 'incident_summary', 'sadas', 'asdad', '2025-08-04', '2025-09-04', 'Alicia', 'Fire', 'excel', 1, '2025-09-04 09:56:44'),
(7, 'incident_summary', 'asdas', 'adasd', '2025-08-04', '2025-09-04', 'Bagong Silangan', 'Hazardous Materials', 'pdf', 1, '2025-09-04 10:06:14'),
(8, 'trend_analysis', 'solisssssssss', 'pogi', '2025-08-04', '2025-09-04', 'Commonwealth', 'Hazardous Materials', 'excel', 1, '2025-09-04 10:07:55'),
(9, 'resource_utilization', 'dadang smilyzer', 'haha', '2025-08-04', '2025-09-04', 'Batasan Hills', 'vehicle-fire', 'excel', 1, '2025-09-04 10:18:14'),
(10, 'resource_utilization', 'dadang smilyzer', 'haha', '2025-08-04', '2025-09-04', 'Batasan Hills', 'vehicle-fire', 'excel', 1, '2025-09-04 10:18:18');

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int(11) NOT NULL,
  `unit_name` varchar(50) NOT NULL,
  `unit_type` varchar(50) NOT NULL,
  `station` varchar(100) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `personnel_count` int(11) NOT NULL DEFAULT 1,
  `equipment` text DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `status` enum('available','dispatched','responding','onscene','returning') NOT NULL DEFAULT 'available',
  `current_location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `unit_name`, `unit_type`, `station`, `barangay`, `personnel_count`, `equipment`, `specialization`, `status`, `current_location`, `latitude`, `longitude`, `created_at`, `updated_at`) VALUES
(1, 'Engine 1', 'Fire Engine', 'Station 1', 'Commonwealth', 4, 'Water pump, Hoses, Breathing apparatus', 'Fire Suppression', 'available', NULL, NULL, NULL, '2025-08-31 09:38:33', '2025-09-13 09:47:31'),
(2, 'Ladder 2', 'Ladder Truck', 'Station 2', 'Batasan Hills', 5, 'Aerial ladder, Extraction tools, Thermal camera', 'Rescue Operations', '', NULL, NULL, NULL, '2025-08-31 09:38:33', '2025-09-02 16:51:45'),
(3, 'Medic 3', 'Ambulance', 'Station 3', 'Payatas', 2, 'Medical supplies, Defibrillator, Stretchers', 'Medical Emergency', 'available', NULL, NULL, NULL, '2025-08-31 09:38:33', '2025-09-13 11:19:45'),
(4, 'Brush Truck 1', 'Brush Truck', 'Station 4', 'Holy Spirit', 3, 'Water tank, Forestry hoses, Brush cutting tools', 'Wildland Fire', '', NULL, NULL, NULL, '2025-08-31 09:38:33', '2025-09-02 16:51:45'),
(5, 'HazMat 1', 'HazMat Unit', 'Station 1', 'Commonwealth', 6, 'Hazardous material suits, Containment equipment, Detection devices', 'Hazardous Materials', 'available', NULL, NULL, NULL, '2025-08-31 09:38:33', '2025-09-13 09:47:34'),
(6, 'Engine 2', 'Fire Engine', 'Station 2', 'Diliman', 5, 'Water pump, Hoses, Ladders', 'Fire Suppression', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(7, 'Rescue 1', 'Rescue Truck', 'Station 3', 'Bagong Silangan', 6, 'Extraction tools, Medical kit', 'Rescue Operations', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(8, 'Medic 4', 'Ambulance', 'Station 4', 'Novaliches', 2, 'Medical supplies, Defibrillator', 'Medical Emergency', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 11:19:47'),
(9, 'Engine 3', 'Fire Engine', 'Station 5', 'Holy Spirit', 4, 'Water pump, Hoses, Axes', 'Fire Suppression', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(10, 'Ladder 3', 'Ladder Truck', 'Station 6', 'Alicia', 5, 'Aerial ladder, Rescue tools', 'Rescue Operations', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(11, 'Brush Truck 2', 'Brush Truck', 'Station 7', 'Payatas', 3, 'Forestry hoses, Brush cutting tools', 'Wildland Fire', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 13:07:18'),
(12, 'Command 1', 'Command Unit', 'Station 1', 'Commonwealth', 2, 'Radios, Tactical maps', 'Incident Command', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(13, 'Medic 5', 'Ambulance', 'Station 2', 'Diliman', 2, 'Medical kit, Oxygen tanks', 'Medical Emergency', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 11:19:48'),
(14, 'Engine 4', 'Fire Engine', 'Station 3', 'Batasan Hills', 5, 'Water pump, Thermal camera', 'Fire Suppression', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(15, 'HazMat 2', 'HazMat Unit', 'Station 4', 'Novaliches', 6, 'Detection devices, Decon equipment', 'Hazardous Materials', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 13:07:19'),
(16, 'Rescue 2', 'Rescue Truck', 'Station 5', 'Bagong Silangan', 6, 'Jaws of Life, Ropes, First Aid', 'Rescue Operations', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(17, 'Engine 5', 'Fire Engine', 'Station 6', 'Commonwealth', 4, 'Water pump, Hoses', 'Fire Suppression', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(18, 'Ladder 4', 'Ladder Truck', 'Station 7', 'Alicia', 5, 'Aerial ladder, Rescue gear', 'Rescue Operations', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(19, 'Medic 6', 'Ambulance', 'Station 8', 'Payatas', 2, 'Medical gear, Defibrillator', 'Medical Emergency', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 11:19:49'),
(20, 'Engine 6', 'Fire Engine', 'Station 9', 'Holy Spirit', 5, 'Water pump, Fire hoses', 'Fire Suppression', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(21, 'Rescue 3', 'Rescue Truck', 'Station 10', 'Diliman', 6, 'Ropes, Extraction tools', 'Rescue Operations', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(22, 'HazMat 3', 'HazMat Unit', 'Station 11', 'Novaliches', 6, 'Protective suits, Decon showers', 'Hazardous Materials', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(23, 'Brush Truck 3', 'Brush Truck', 'Station 12', 'Bagong Silangan', 3, 'Forestry hoses, Chainsaws', 'Wildland Fire', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 13:07:20'),
(24, 'Command 2', 'Command Unit', 'Station 13', 'Commonwealth', 2, 'Radios, Tactical maps', 'Incident Command', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(25, 'Medic 7', 'Ambulance', 'Station 14', 'Alicia', 2, 'Medical kit, Oxygen tanks', 'Medical Emergency', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 11:19:50'),
(26, 'Engine 7', 'Fire Engine', 'Station 15', 'Batasan Hills', 4, 'Water pump, Fire hoses', 'Fire Suppression', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(27, 'Ladder 5', 'Ladder Truck', 'Station 16', 'Holy Spirit', 5, 'Aerial ladder, Thermal camera', 'Rescue Operations', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(28, 'Rescue 4', 'Rescue Truck', 'Station 17', 'Payatas', 6, 'Jaws of Life, Ropes', 'Rescue Operations', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(29, 'Engine 8', 'Fire Engine', 'Station 18', 'Diliman', 5, 'Water pump, Hoses', 'Fire Suppression', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25'),
(30, 'HazMat 4', 'HazMat Unit', 'Station 19', 'Commonwealth', 6, 'HazMat suits, Detection devices', 'Hazardous Materials', 'available', NULL, NULL, NULL, '2025-09-13 09:51:25', '2025-09-13 09:51:25');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `communications`
--
ALTER TABLE `communications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`);

--
-- Indexes for table `dispatches`
--
ALTER TABLE `dispatches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `hospitals`
--
ALTER TABLE `hospitals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barangay` (`barangay`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_type` (`incident_type`),
  ADD KEY `barangay` (`barangay`),
  ADD KEY `status` (`status`),
  ADD KEY `priority` (`priority`);

--
-- Indexes for table `incident_logs`
--
ALTER TABLE `incident_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unit_name` (`unit_name`),
  ADD KEY `unit_type` (`unit_type`),
  ADD KEY `status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `communications`
--
ALTER TABLE `communications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `dispatches`
--
ALTER TABLE `dispatches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `hospitals`
--
ALTER TABLE `hospitals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `incident_logs`
--
ALTER TABLE `incident_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `communications`
--
ALTER TABLE `communications`
  ADD CONSTRAINT `communications_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dispatches`
--
ALTER TABLE `dispatches`
  ADD CONSTRAINT `dispatches_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dispatches_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
