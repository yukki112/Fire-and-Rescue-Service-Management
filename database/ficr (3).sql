-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: Localhost:3307
-- Generation Time: Sep 14, 2025 at 10:29 AM
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
-- Database: `ficr`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'resolve', 'violations', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-09 10:37:32'),
(2, 1, 'resolve', 'violations', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-09 10:37:36'),
(3, 1, 'update', 'inspection_checklists', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-09 10:38:16'),
(4, 1, 'delete', 'inspection_checklists', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-09 10:38:19'),
(5, 1, 'create', 'inspection_checklists', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-09 10:38:25'),
(6, 1, 'update', 'violations', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-09 10:38:42'),
(7, 1, 'renew', 'clearances', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-09 10:38:58'),
(8, 1, 'renew', 'clearances', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-09 10:39:09');

-- --------------------------------------------------------

--
-- Table structure for table `checklist_items`
--

CREATE TABLE `checklist_items` (
  `id` int(11) NOT NULL,
  `checklist_id` int(11) NOT NULL,
  `item_text` text NOT NULL,
  `item_type` enum('yes_no','rating','text','number') NOT NULL DEFAULT 'yes_no',
  `weight` decimal(5,2) DEFAULT 1.00,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `order_index` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `checklist_items`
--

INSERT INTO `checklist_items` (`id`, `checklist_id`, `item_text`, `item_type`, `weight`, `is_required`, `order_index`, `created_at`, `updated_at`) VALUES
(1, 1, 'Are fire extinguishers properly mounted and accessible?', 'yes_no', 1.00, 1, 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(2, 1, 'Are exit signs clearly visible and illuminated?', 'yes_no', 1.00, 1, 2, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(3, 1, 'Are emergency exits unobstructed?', 'yes_no', 1.00, 1, 3, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(4, 1, 'Are smoke detectors functional?', 'yes_no', 1.00, 1, 4, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(5, 1, 'Are fire alarms tested regularly?', 'yes_no', 1.00, 1, 5, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(6, 1, 'Rate the overall fire safety preparedness', 'rating', 2.00, 1, 6, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(7, 1, 'Number of fire safety violations found', 'number', 1.50, 0, 7, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(8, 1, 'Additional fire safety comments', 'text', 0.50, 0, 8, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(9, 1, 'Are fire evacuation plans posted?', 'yes_no', 1.00, 1, 9, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(10, 1, 'Are flammable materials properly stored?', 'yes_no', 1.00, 1, 10, '2025-09-05 19:50:14', '2025-09-05 19:50:14');

-- --------------------------------------------------------

--
-- Table structure for table `clearances`
--

CREATE TABLE `clearances` (
  `id` int(11) NOT NULL,
  `establishment_id` int(11) NOT NULL,
  `inspection_id` int(11) NOT NULL,
  `clearance_number` varchar(50) NOT NULL,
  `type` enum('fire_safety','business_permit','occupancy') NOT NULL DEFAULT 'fire_safety',
  `issue_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('active','expired','revoked','suspended') NOT NULL DEFAULT 'active',
  `issued_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clearances`
--

INSERT INTO `clearances` (`id`, `establishment_id`, `inspection_id`, `clearance_number`, `type`, `issue_date`, `expiry_date`, `status`, `issued_by`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'FS-2025-0001', 'fire_safety', '2025-09-10', '2026-09-10', 'active', 1, 'Full compliance - 1 year validity', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(2, 2, 2, 'FS-2025-0002', 'fire_safety', '2025-09-11', '2026-09-11', 'active', 2, 'Good compliance - 1 year validity', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(3, 3, 3, 'FS-2025-0003', 'fire_safety', '2025-09-12', '2025-12-12', 'active', 3, 'Partial compliance - 3 months validity', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(4, 4, 4, 'FS-2025-0004', 'fire_safety', '2025-09-13', '2025-12-13', 'active', 4, 'Conditional compliance - 3 months', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(5, 5, 5, 'FS-2025-0005', 'fire_safety', '2025-09-14', '2026-09-14', 'active', 5, 'Full compliance - 1 year', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(6, 6, 6, 'FS-2025-0006', 'fire_safety', '2025-09-15', '2025-10-15', 'active', 6, 'Temporary clearance - 1 month', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(7, 7, 7, 'FS-2025-0007', 'fire_safety', '2025-09-16', '2026-09-16', 'active', 7, 'Excellent compliance - 1 year', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(8, 8, 8, 'FS-2025-0008', 'fire_safety', '2025-09-17', '2025-12-17', 'active', 8, 'Conditional - 3 months', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(9, 9, 9, 'FS-2025-0009', 'fire_safety', '2025-09-18', '2025-10-18', 'active', 9, 'Provisional - 1 month', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(10, 10, 10, 'FS-2025-0010', 'fire_safety', '2025-09-19', '2026-09-30', 'active', 10, 'Good compliance - 1 year', '2025-09-05 19:50:14', '2025-09-09 10:39:09');

-- --------------------------------------------------------

--
-- Table structure for table `establishments`
--

CREATE TABLE `establishments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `owner_name` varchar(100) NOT NULL,
  `owner_contact` varchar(20) DEFAULT NULL,
  `owner_email` varchar(100) DEFAULT NULL,
  `occupancy_type` enum('residential','commercial','industrial','institutional','mixed') NOT NULL,
  `occupancy_count` int(11) DEFAULT NULL,
  `floor_area` decimal(10,2) DEFAULT NULL,
  `floors` int(11) DEFAULT 1,
  `status` enum('active','inactive','closed') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `establishments`
--

INSERT INTO `establishments` (`id`, `name`, `type`, `address`, `barangay`, `latitude`, `longitude`, `owner_name`, `owner_contact`, `owner_email`, `occupancy_type`, `occupancy_count`, `floor_area`, `floors`, `status`, `created_at`, `updated_at`) VALUES
(1, 'ABC Shopping Mall', 'Commercial', '123 Main Street', 'Poblacion', 14.59951200, 120.98422200, 'John Smith', '09123456789', 'john.smith@example.com', 'commercial', 500, 5000.00, 3, 'active', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(2, 'XYZ Manufacturing', 'Industrial', '456 Industrial Road', 'Bagong Silang', 14.60123400, 120.98567800, 'Maria Garcia', '09234567890', 'maria.garcia@example.com', 'industrial', 200, 8000.00, 2, 'active', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(3, 'Green Valley Hospital', 'Institutional', '789 Health Avenue', 'Libtong', 14.59876500, 120.98654300, 'Dr. Robert Lim', '09345678901', 'robert.lim@example.com', 'institutional', 300, 6000.00, 4, 'active', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(4, 'Sunset Apartments', 'Residential', '321 Sunset Boulevard', 'San Jose', 14.60234500, 120.98765400, 'Susan Tan', '09456789012', 'susan.tan@example.com', 'residential', 100, 3000.00, 5, 'active', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(5, 'Tech Hub Office', 'Commercial', '654 Tech Park', 'Poblacion', 14.60012300, 120.98876500, 'Michael Chen', '09567890123', 'michael.chen@example.com', 'commercial', 150, 2500.00, 2, 'active', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(6, 'Food Court Complex', 'Commercial', '987 Food Street', 'Bagong Silang', 14.59987600, 120.98987600, 'Lisa Wong', '09678901234', 'lisa.wong@example.com', 'commercial', 80, 2000.00, 1, 'active', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(7, 'Metro High School', 'Institutional', '741 Education Road', 'Libtong', 14.60345600, 120.99012300, 'Principal David Lee', '09789012345', 'david.lee@example.com', 'institutional', 800, 4000.00, 3, 'active', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(8, 'Star Factory', 'Industrial', '852 Production Avenue', 'San Jose', 14.60456700, 120.99123400, 'James Wilson', '09890123456', 'james.wilson@example.com', 'industrial', 120, 3500.00, 2, 'active', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(9, 'Garden Residences', 'Residential', '963 Garden Street', 'Poblacion', 14.60567800, 120.99234500, 'Sarah Martinez', '09901234567', 'sarah.martinez@example.com', 'residential', 60, 1800.00, 4, 'active', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(10, 'City Market', 'Commercial', '159 Market Road', 'Bagong Silang', 14.60678900, 120.99345600, 'Carlos Rodriguez', '09112345678', 'carlos.rodriguez@example.com', 'commercial', 40, 1200.00, 1, 'active', '2025-09-05 19:50:14', '2025-09-05 19:50:14');

-- --------------------------------------------------------

--
-- Table structure for table `inspection_checklists`
--

CREATE TABLE `inspection_checklists` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inspection_checklists`
--

INSERT INTO `inspection_checklists` (`id`, `name`, `description`, `category`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Fire Safety Checklist', 'Comprehensive fire safety inspection checklist', 'Fire Safety', 1, 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(4, 'Emergency Exits', 'Emergency exit routes and signage', 'Safety', 1, 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(5, 'Fire Extinguishers', 'Fire extinguisher placement and maintenance', 'Fire Safety', 1, 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(6, 'Smoke Detectors', 'Smoke detector functionality', 'Fire Safety', 1, 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(7, 'Sprinkler Systems', 'Sprinkler system inspection', 'Fire Safety', 1, 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(8, 'HVAC Systems', 'Heating and cooling systems', 'Mechanical', 1, 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(9, 'Plumbing', 'Plumbing and water systems', 'Mechanical', 1, 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(10, 'General Safety', 'General safety compliance', 'Safety', 1, 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(11, 'sdd', 'sad', 'asds', 1, 1, '2025-09-09 10:38:25', '2025-09-09 10:38:25');

-- --------------------------------------------------------

--
-- Table structure for table `inspection_item_results`
--

CREATE TABLE `inspection_item_results` (
  `id` int(11) NOT NULL,
  `inspection_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `response` text DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `is_compliant` tinyint(1) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `evidence_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inspection_item_results`
--

INSERT INTO `inspection_item_results` (`id`, `inspection_id`, `item_id`, `response`, `score`, `is_compliant`, `notes`, `evidence_file`, `created_at`) VALUES
(1, 1, 1, 'yes', 1.00, 1, 'All extinguishers properly mounted', NULL, '2025-09-05 19:50:14'),
(2, 1, 2, 'yes', 1.00, 1, 'Exit signs clearly visible', NULL, '2025-09-05 19:50:14'),
(3, 1, 3, 'yes', 1.00, 1, 'Exits completely clear', NULL, '2025-09-05 19:50:14'),
(4, 1, 4, 'yes', 1.00, 1, 'All detectors functional', NULL, '2025-09-05 19:50:14'),
(5, 1, 5, 'yes', 1.00, 1, 'Regular testing documented', NULL, '2025-09-05 19:50:14'),
(6, 1, 6, '5', 10.00, 1, 'Excellent preparedness', NULL, '2025-09-05 19:50:14'),
(7, 1, 7, '0', 0.00, 1, 'No violations found', NULL, '2025-09-05 19:50:14'),
(8, 1, 8, 'No issues observed', 0.50, 1, 'Very compliant establishment', NULL, '2025-09-05 19:50:14'),
(9, 1, 9, 'yes', 1.00, 1, 'Plans properly posted', NULL, '2025-09-05 19:50:14'),
(10, 1, 10, 'yes', 1.00, 1, 'Flammables properly stored', NULL, '2025-09-05 19:50:14');

-- --------------------------------------------------------

--
-- Table structure for table `inspection_results`
--

CREATE TABLE `inspection_results` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `establishment_id` int(11) NOT NULL,
  `inspector_id` int(11) NOT NULL,
  `inspection_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `overall_score` decimal(5,2) DEFAULT NULL,
  `overall_rating` enum('excellent','good','fair','poor','critical') DEFAULT NULL,
  `status` enum('compliant','non_compliant','partial_compliant') NOT NULL,
  `summary` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `next_inspection_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inspection_results`
--

INSERT INTO `inspection_results` (`id`, `schedule_id`, `establishment_id`, `inspector_id`, `inspection_date`, `start_time`, `end_time`, `overall_score`, `overall_rating`, `status`, `summary`, `recommendations`, `next_inspection_date`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, '2025-09-10', '09:05:00', '10:30:00', 95.00, 'excellent', 'compliant', 'Excellent fire safety compliance', 'Continue regular maintenance', '2026-09-10', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(2, 2, 2, 2, '2025-09-11', '10:35:00', '12:00:00', 85.00, 'good', 'compliant', 'Good overall safety measures', 'Improve emergency exit signage', '2026-09-11', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(3, 3, 3, 3, '2025-09-12', '14:05:00', '15:45:00', 75.00, 'fair', 'partial_compliant', 'Fair compliance with minor issues', 'Fix smoke detectors in west wing', '2025-12-12', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(4, 4, 4, 4, '2025-09-13', '11:05:00', '12:30:00', 65.00, 'fair', 'partial_compliant', 'Several safety violations found', 'Install additional fire extinguishers', '2025-12-13', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(5, 5, 5, 5, '2025-09-14', '15:35:00', '17:00:00', 90.00, 'good', 'compliant', 'Good safety protocols', 'Maintain current standards', '2026-09-14', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(6, 6, 6, 6, '2025-09-15', '09:35:00', '11:15:00', 55.00, 'poor', 'non_compliant', 'Multiple serious violations', 'Immediate corrective actions required', '2025-10-15', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(7, 7, 7, 7, '2025-09-16', '13:05:00', '14:50:00', 98.00, 'excellent', 'compliant', 'Outstanding safety compliance', 'Excellent maintenance', '2026-09-16', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(8, 8, 8, 8, '2025-09-17', '10:05:00', '11:40:00', 70.00, 'fair', 'partial_compliant', 'Average compliance with room for improvement', 'Train staff on emergency procedures', '2025-12-17', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(9, 9, 9, 9, '2025-09-18', '16:05:00', '17:30:00', 45.00, 'poor', 'non_compliant', 'Critical safety violations found', 'Immediate shutdown until compliance', '2025-10-18', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(10, 10, 10, 10, '2025-09-19', '08:35:00', '10:20:00', 88.00, 'good', 'compliant', 'Good safety measures implemented', 'Continue regular inspections', '2026-09-19', '2025-09-05 19:50:14', '2025-09-05 19:50:14');

-- --------------------------------------------------------

--
-- Table structure for table `inspection_roles`
--

CREATE TABLE `inspection_roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `permissions` text NOT NULL COMMENT 'JSON encoded permissions',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inspection_roles`
--

INSERT INTO `inspection_roles` (`id`, `role_name`, `permissions`, `created_at`, `updated_at`) VALUES
(1, 'Chief Inspector', '{\"view_all\": true, \"edit_all\": true, \"delete_all\": true, \"approve_reports\": true}', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(2, 'Senior Inspector', '{\"view_all\": true, \"edit_assigned\": true, \"delete_assigned\": false, \"approve_reports\": false}', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(3, 'Junior Inspector', '{\"view_assigned\": true, \"edit_assigned\": true, \"delete_assigned\": false, \"approve_reports\": false}', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(4, 'Trainee Inspector', '{\"view_assigned\": true, \"edit_assigned\": false, \"delete_assigned\": false, \"approve_reports\": false}', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(5, 'Safety Officer', '{\"view_all\": true, \"edit_all\": false, \"delete_all\": false, \"approve_reports\": false}', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(6, 'Compliance Manager', '{\"view_all\": true, \"edit_all\": true, \"delete_all\": false, \"approve_reports\": true}', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(7, 'Quality Assurance', '{\"view_all\": true, \"edit_all\": false, \"delete_all\": false, \"approve_reports\": false}', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(8, 'Administrator', '{\"view_all\": true, \"edit_all\": true, \"delete_all\": true, \"approve_reports\": true}', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(9, 'Auditor', '{\"view_all\": true, \"edit_all\": false, \"delete_all\": false, \"approve_reports\": false}', '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(10, 'Supervisor', '{\"view_all\": true, \"edit_all\": true, \"delete_all\": false, \"approve_reports\": true}', '2025-09-05 19:50:14', '2025-09-05 19:50:14');

-- --------------------------------------------------------

--
-- Table structure for table `inspection_schedules`
--

CREATE TABLE `inspection_schedules` (
  `id` int(11) NOT NULL,
  `establishment_id` int(11) NOT NULL,
  `scheduled_date` date NOT NULL,
  `scheduled_time` time NOT NULL,
  `assigned_inspector` int(11) NOT NULL,
  `checklist_id` int(11) NOT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `status` enum('scheduled','in_progress','completed','cancelled','rescheduled') NOT NULL DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inspection_schedules`
--

INSERT INTO `inspection_schedules` (`id`, `establishment_id`, `scheduled_date`, `scheduled_time`, `assigned_inspector`, `checklist_id`, `priority`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-09-10', '09:00:00', 1, 1, 'low', 'scheduled', 'Routine fire safety inspection', 1, '2025-09-05 19:50:14', '2025-09-05 20:33:20'),
(2, 2, '2025-09-11', '10:30:00', 2, 1, 'medium', 'scheduled', 'Annual inspection', 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(3, 3, '2025-09-12', '14:00:00', 3, 1, 'high', 'scheduled', 'Follow-up inspection', 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(4, 4, '2025-09-13', '11:00:00', 4, 1, 'low', 'scheduled', 'New establishment inspection', 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(5, 5, '2025-09-14', '15:30:00', 5, 1, 'medium', 'scheduled', 'Complaint investigation', 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(6, 6, '2025-09-15', '09:30:00', 6, 1, 'high', 'scheduled', 'Random inspection', 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(7, 7, '2025-09-16', '13:00:00', 7, 1, 'medium', 'scheduled', 'Scheduled maintenance check', 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(8, 8, '2025-09-17', '10:00:00', 8, 1, 'low', 'scheduled', 'Pre-license inspection', 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(9, 9, '2025-09-18', '16:00:00', 9, 1, 'high', 'scheduled', 'Post-renovation inspection', 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(10, 10, '2025-09-19', '08:30:00', 10, 1, 'medium', 'scheduled', 'Regular safety audit', 1, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(11, 1, '2025-09-10', '16:24:00', 10, 5, 'low', 'scheduled', 'asd', 1, '2025-09-05 20:18:42', '2025-09-05 20:18:42'),
(12, 1, '2025-09-10', '16:24:00', 10, 5, 'low', 'scheduled', 'asd', 1, '2025-09-05 20:32:35', '2025-09-05 20:32:35'),
(13, 6, '2025-09-17', '16:36:00', 6, 6, 'medium', 'scheduled', 'asdasdad', 1, '2025-09-05 20:33:40', '2025-09-05 20:33:40');

-- --------------------------------------------------------

--
-- Table structure for table `user_inspection_roles`
--

CREATE TABLE `user_inspection_roles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_inspection_roles`
--

INSERT INTO `user_inspection_roles` (`id`, `user_id`, `role_id`, `assigned_by`, `assigned_at`) VALUES
(1, 1, 1, 1, '2025-09-05 19:50:14'),
(2, 2, 2, 1, '2025-09-05 19:50:14'),
(3, 1, 8, 1, '2025-09-05 19:50:14'),
(4, 2, 3, 1, '2025-09-05 19:50:14'),
(5, 1, 6, 1, '2025-09-05 19:50:14'),
(6, 2, 5, 1, '2025-09-05 19:50:14'),
(7, 1, 10, 1, '2025-09-05 19:50:14'),
(8, 2, 7, 1, '2025-09-05 19:50:14'),
(9, 1, 9, 1, '2025-09-05 19:50:14'),
(10, 2, 4, 1, '2025-09-05 19:50:14');

-- --------------------------------------------------------

--
-- Table structure for table `violations`
--

CREATE TABLE `violations` (
  `id` int(11) NOT NULL,
  `inspection_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `violation_code` varchar(20) NOT NULL,
  `description` text NOT NULL,
  `severity` enum('minor','major','critical') NOT NULL DEFAULT 'minor',
  `corrective_action` text NOT NULL,
  `deadline` date NOT NULL,
  `status` enum('open','in_progress','resolved','overdue') NOT NULL DEFAULT 'open',
  `fine_amount` decimal(10,2) DEFAULT NULL,
  `paid_amount` decimal(10,2) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violations`
--

INSERT INTO `violations` (`id`, `inspection_id`, `item_id`, `violation_code`, `description`, `severity`, `corrective_action`, `deadline`, `status`, `fine_amount`, `paid_amount`, `payment_date`, `resolution_notes`, `created_at`, `updated_at`) VALUES
(1, 3, 4, 'FS-VIOL-001', 'Smoke detector not functional in west wing', 'minor', 'Replace or repair smoke detector', '2025-09-26', 'open', 5000.00, NULL, NULL, NULL, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(2, 4, 1, 'FS-VIOL-002', 'Fire extinguisher not properly mounted', 'minor', 'Properly mount fire extinguisher', '2025-09-20', 'resolved', 3000.00, 0.00, NULL, 'asdad', '2025-09-05 19:50:14', '2025-09-09 10:34:16'),
(3, 4, 10, 'FS-VIOL-003', 'Flammable materials improperly stored', 'major', 'Proper storage of flammable materials', '2025-09-27', 'open', 10000.00, NULL, NULL, NULL, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(4, 6, 3, 'asdasd', 'asdasd', 'major', 'asdasd', '2025-09-07', 'resolved', 1333.00, 1332.00, '2025-09-07', NULL, '2025-09-05 19:50:14', '2025-09-07 06:56:58'),
(5, 6, 9, 'FS-VIOL-005', 'Fire evacuation plans not posted', 'major', 'Post evacuation plans in visible areas', '2025-09-22', 'open', 8000.00, NULL, NULL, NULL, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(6, 8, 5, 'FS-VIOL-006', 'No documentation of fire alarm testing', 'minor', 'Conduct and document regular testing', '2025-09-24', 'open', 4000.00, NULL, NULL, NULL, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(7, 9, 2, 'FS-VIOL-007', 'Exit signs not illuminated', 'major', 'Repair or replace exit signs', '2025-09-25', 'open', 12000.00, NULL, NULL, NULL, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(8, 9, 6, 'FS-VIOL-008', 'Poor fire safety preparedness', 'critical', 'Implement comprehensive safety training', '2025-09-23', 'open', 25000.00, NULL, NULL, NULL, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(9, 9, 7, 'FS-VIOL-009', 'Multiple fire safety violations', 'critical', 'Address all violations immediately', '2025-09-21', 'resolved', 30000.00, NULL, NULL, 'asd', '2025-09-05 19:50:14', '2025-09-09 10:37:32'),
(10, 10, 8, 'FS-VIOL-010', 'Inadequate fire safety documentation', 'minor', 'Maintain proper safety records', '2025-09-28', 'open', 6000.00, NULL, NULL, NULL, '2025-09-05 19:50:14', '2025-09-05 19:50:14'),
(11, 8, 5, 'VIOL-2025-21SADASDAS', '11111111', 'critical', '11111111111', '2025-09-21', 'resolved', 9999.99, 1212121.00, '2025-09-15', 'sss', '2025-09-07 06:16:51', '2025-09-09 10:38:42'),
(12, 8, 5, 'VIOL-2025-219', 'SDAASD', 'critical', 'ASDASD', '2025-09-21', 'open', 99999999.99, NULL, NULL, NULL, '2025-09-07 06:17:23', '2025-09-07 06:17:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `checklist_items`
--
ALTER TABLE `checklist_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `checklist_id` (`checklist_id`);

--
-- Indexes for table `clearances`
--
ALTER TABLE `clearances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clearance_number` (`clearance_number`),
  ADD KEY `establishment_id` (`establishment_id`),
  ADD KEY `inspection_id` (`inspection_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `establishments`
--
ALTER TABLE `establishments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barangay` (`barangay`),
  ADD KEY `type` (`type`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `inspection_checklists`
--
ALTER TABLE `inspection_checklists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category` (`category`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `inspection_item_results`
--
ALTER TABLE `inspection_item_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inspection_id` (`inspection_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `inspection_results`
--
ALTER TABLE `inspection_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `establishment_id` (`establishment_id`);

--
-- Indexes for table `inspection_roles`
--
ALTER TABLE `inspection_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `inspection_schedules`
--
ALTER TABLE `inspection_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `establishment_id` (`establishment_id`),
  ADD KEY `checklist_id` (`checklist_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `user_inspection_roles`
--
ALTER TABLE `user_inspection_roles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `violations`
--
ALTER TABLE `violations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inspection_id` (`inspection_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `checklist_items`
--
ALTER TABLE `checklist_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `clearances`
--
ALTER TABLE `clearances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `establishments`
--
ALTER TABLE `establishments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `inspection_checklists`
--
ALTER TABLE `inspection_checklists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `inspection_item_results`
--
ALTER TABLE `inspection_item_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `inspection_results`
--
ALTER TABLE `inspection_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `inspection_roles`
--
ALTER TABLE `inspection_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `inspection_schedules`
--
ALTER TABLE `inspection_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `user_inspection_roles`
--
ALTER TABLE `user_inspection_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `violations`
--
ALTER TABLE `violations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_auditlogs_user` FOREIGN KEY (`user_id`) REFERENCES `frsm`.`users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `checklist_items`
--
ALTER TABLE `checklist_items`
  ADD CONSTRAINT `checklist_items_ibfk_1` FOREIGN KEY (`checklist_id`) REFERENCES `inspection_checklists` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `clearances`
--
ALTER TABLE `clearances`
  ADD CONSTRAINT `clearances_ibfk_1` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `clearances_ibfk_2` FOREIGN KEY (`inspection_id`) REFERENCES `inspection_results` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inspection_item_results`
--
ALTER TABLE `inspection_item_results`
  ADD CONSTRAINT `inspection_item_results_ibfk_1` FOREIGN KEY (`inspection_id`) REFERENCES `inspection_results` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inspection_item_results_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `checklist_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inspection_results`
--
ALTER TABLE `inspection_results`
  ADD CONSTRAINT `inspection_results_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `inspection_schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inspection_results_ibfk_2` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inspection_schedules`
--
ALTER TABLE `inspection_schedules`
  ADD CONSTRAINT `inspection_schedules_ibfk_1` FOREIGN KEY (`establishment_id`) REFERENCES `establishments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inspection_schedules_ibfk_2` FOREIGN KEY (`checklist_id`) REFERENCES `inspection_checklists` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_inspection_roles`
--
ALTER TABLE `user_inspection_roles`
  ADD CONSTRAINT `user_inspection_roles_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `inspection_roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `violations`
--
ALTER TABLE `violations`
  ADD CONSTRAINT `violations_ibfk_1` FOREIGN KEY (`inspection_id`) REFERENCES `inspection_results` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `violations_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `checklist_items` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
