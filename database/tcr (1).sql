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
-- Database: `tcr`
--

-- --------------------------------------------------------

--
-- Table structure for table `certifications`
--

CREATE TABLE `certifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `certification_number` varchar(50) NOT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('active','expired','revoked') NOT NULL DEFAULT 'active',
  `verification_url` varchar(255) DEFAULT NULL,
  `issuing_authority` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certifications`
--

INSERT INTO `certifications` (`id`, `user_id`, `course_id`, `certification_number`, `issue_date`, `expiry_date`, `status`, `verification_url`, `issuing_authority`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'CERT-FSB-001', '2025-01-16', '2027-01-16', 'active', 'http://verify.tcr/cert/EMP001', 'BFP Academy', 'Juan completed Fire Safety Basics', '2025-09-05 15:45:27', '2025-09-05 15:45:27'),
(2, 2, 1, 'CERT-FSB-002', '2025-01-16', '2027-01-16', 'revoked', 'http://verify.tcr/cert/EMP002', 'BFP Academy', 'Maria completed Fire Safety Basics', '2025-09-05 15:45:27', '2025-09-05 18:08:04'),
(3, 3, 2, 'CERT-CPR-003', '2025-02-08', '2026-02-08', 'active', 'http://verify.tcr/cert/EMP003', 'BFP Academy', 'Pedro certified in First Aid & CPR', '2025-09-05 15:45:27', '2025-09-05 15:45:27'),
(4, 4, 2, 'CERT-CPR-004', '2025-02-08', '2026-02-08', 'active', 'http://verify.tcr/cert/EMP0041', 'BFP Academy', 'Ana certified in First Aid & CPR', '2025-09-05 15:45:27', '2025-09-05 17:25:28'),
(5, 5, 3, 'CERT-DR-005', '0000-00-00', NULL, 'active', NULL, 'BFP Academy', 'Luis registered for Disaster Response, pending completion', '2025-09-05 15:45:27', '2025-09-05 15:45:27'),
(6, 1, 4, 'CERT-HAZ-006', '0000-00-00', NULL, 'active', NULL, 'BFP Academy', 'Juan registered for Hazardous Materials Handling', '2025-09-05 15:45:27', '2025-09-05 15:45:27'),
(7, 2, 5, 'CERT-RES-007', '0000-00-00', NULL, 'active', NULL, 'BFP Academy', 'Maria registered for Rescue Operations', '2025-09-05 15:45:27', '2025-09-05 15:45:27'),
(8, 3, 6, 'CERT-LEAD-008', '0000-00-00', NULL, 'active', NULL, 'BFP Academy', 'Pedro registered for Leadership in Emergencies', '2025-09-05 15:45:27', '2025-09-05 15:45:27'),
(9, 4, 7, 'CERT-FFT-009', '0000-00-00', NULL, 'active', NULL, 'BFP Academy', 'Ana registered for Firefighting Tactics', '2025-09-05 15:45:27', '2025-09-05 15:45:27'),
(10, 5, 8, 'CERT-COMM-010', '0000-00-00', NULL, 'active', NULL, 'BFP Academy', 'Luis registered for Emergency Communications', '2025-09-05 15:45:27', '2025-09-05 15:45:27'),
(12, 8, 2, '22831763', '2025-09-06', '2025-10-02', 'expired', 'https://www.facebook.com/s2.xwoo', 'BFP Academy', 'asdasd', '2025-09-05 17:44:09', '2025-09-05 17:44:09');

-- --------------------------------------------------------

--
-- Table structure for table `training_assessments`
--

CREATE TABLE `training_assessments` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `assessment_type` varchar(50) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `max_score` decimal(5,2) NOT NULL,
  `passing_score` decimal(5,2) NOT NULL,
  `actual_score` decimal(5,2) DEFAULT NULL,
  `assessment_date` date DEFAULT NULL,
  `evaluator` varchar(100) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `training_assessments`
--

INSERT INTO `training_assessments` (`id`, `enrollment_id`, `assessment_type`, `title`, `description`, `max_score`, `passing_score`, `actual_score`, `assessment_date`, `evaluator`, `feedback`, `created_at`, `updated_at`) VALUES
(1, 1, 'exam', 'Final Written Exam', 'Basic fire safety exam.', 100.00, 75.00, 90.00, '2025-01-15', 'Capt. Santos', 'Excellent work', '2025-09-05 15:44:59', '2025-09-05 15:44:59'),
(2, 2, 'exam', 'Final Written Exam', 'Basic fire safety exam.', 100.00, 75.00, 85.00, '2025-01-15', 'Capt. Santos', 'Good effort', '2025-09-05 15:44:59', '2025-09-05 15:44:59'),
(3, 3, 'practical', 'CPR Demo', 'Hands-on CPR performance.', 100.00, 80.00, 89.00, '2025-02-07', 'Nurse Dela Cruz', 'Very good skills', '2025-09-05 15:44:59', '2025-09-05 15:44:59'),
(4, 4, 'practical', 'First Aid Demo', 'Bandaging and first aid.', 100.00, 80.00, 80.00, '2025-02-07', 'Nurse Dela Cruz', 'Meets requirements', '2025-09-05 15:44:59', '2025-09-05 15:44:59'),
(5, 5, 'quiz', 'Disaster Response Quiz', 'Pre-assessment test.', 50.00, 35.00, NULL, NULL, 'Chief Reyes', NULL, '2025-09-05 15:44:59', '2025-09-05 15:44:59'),
(6, 6, 'quiz', 'Disaster Response Quiz', 'Pre-assessment test.', 50.00, 35.00, NULL, NULL, 'Chief Reyes', NULL, '2025-09-05 15:44:59', '2025-09-05 15:44:59'),
(7, 7, 'exam', 'Hazmat Exam', 'Safety procedures assessment.', 100.00, 70.00, 60.00, NULL, 'Engr. Mendoza', 'not ready yet', '2025-09-05 15:44:59', '2025-09-05 17:57:29'),
(8, 8, 'exam', 'Rescue Ops Exam', 'Field rescue test.', 100.00, 75.00, NULL, NULL, 'Lt. Garcia', NULL, '2025-09-05 15:44:59', '2025-09-05 15:44:59'),
(9, 9, 'exam', 'Leadership Exam', 'Leadership scenarios.', 100.00, 80.00, NULL, NULL, 'Maj. Cruz', NULL, '2025-09-05 15:44:59', '2025-09-05 15:44:59'),
(10, 10, 'exam', 'Firefighting Tactics Exam', 'Advanced firefighting knowledge.', 100.00, 85.00, NULL, NULL, 'Capt. Villanueva', NULL, '2025-09-05 15:44:59', '2025-09-05 15:44:59');

-- --------------------------------------------------------

--
-- Table structure for table `training_courses`
--

CREATE TABLE `training_courses` (
  `id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_hours` int(11) NOT NULL,
  `validity_months` int(11) DEFAULT NULL,
  `category` varchar(50) NOT NULL,
  `prerequisites` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `training_courses`
--

INSERT INTO `training_courses` (`id`, `course_code`, `course_name`, `description`, `duration_hours`, `validity_months`, `category`, `prerequisites`, `status`, `created_at`, `updated_at`) VALUES
(1, 'BFP101', 'Fire Safety Basics', 'Introduction to fire prevention and safety protocols.', 20, 24, 'Safety', NULL, 'active', '2025-09-05 15:41:59', '2025-09-05 15:41:59'),
(2, 'BFP102', 'First Aid & CPR', 'Emergency first aid and CPR training.', 15, 12, 'Medical', NULL, 'active', '2025-09-05 15:41:59', '2025-09-05 15:41:59'),
(3, 'BFP103', 'Disaster Response', 'Procedures during natural disasters.', 25, 24, 'Other', 'BFP101', 'active', '2025-09-05 15:41:59', '2025-09-05 15:53:14'),
(4, 'BFP104', 'Hazardous Materials Handling', 'Safe handling of hazardous materials.', 30, 18, 'Other', 'BFP101', 'inactive', '2025-09-05 15:41:59', '2025-09-05 15:52:42'),
(5, 'BFP105', 'Rescue Operations', 'Basic rescue techniques.', 40, 24, 'Rescue', 'BFP101', 'active', '2025-09-05 15:41:59', '2025-09-05 15:41:59'),
(6, 'BFP106', 'Leadership in Emergencies', 'Team leadership during crises.', 20, 12, 'Management', 'BFP103', 'active', '2025-09-05 15:41:59', '2025-09-05 15:41:59'),
(7, 'BFP107', 'Firefighting Tactics', 'Advanced firefighting procedures.', 50, 36, 'Operations', 'BFP101', 'active', '2025-09-05 15:41:59', '2025-09-05 15:41:59'),
(8, 'BFP108', 'Emergency Communications', 'Effective communication during emergencies.', 10, 12, 'Communication', NULL, 'active', '2025-09-05 15:41:59', '2025-09-05 15:41:59'),
(9, 'BFP109', 'Vehicle Extrication', 'Techniques for vehicle accident rescues.', 35, 24, 'Rescue', 'BFP105', 'active', '2025-09-05 15:41:59', '2025-09-05 15:41:59'),
(10, 'BFP110', 'Incident Command System', 'Managing large-scale incidents.', 45, 24, 'Management', 'BFP106', 'active', '2025-09-05 15:41:59', '2025-09-05 15:41:59');

-- --------------------------------------------------------

--
-- Table structure for table `training_enrollments`
--

CREATE TABLE `training_enrollments` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `enrollment_date` date NOT NULL,
  `status` enum('registered','attending','completed','failed','cancelled') NOT NULL DEFAULT 'registered',
  `attendance_percentage` decimal(5,2) DEFAULT NULL,
  `final_grade` decimal(5,2) DEFAULT NULL,
  `certificate_issued` tinyint(1) DEFAULT 0,
  `certificate_issue_date` date DEFAULT NULL,
  `certificate_expiry_date` date DEFAULT NULL,
  `evaluation_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `training_enrollments`
--

INSERT INTO `training_enrollments` (`id`, `session_id`, `user_id`, `enrollment_date`, `status`, `attendance_percentage`, `final_grade`, `certificate_issued`, `certificate_issue_date`, `certificate_expiry_date`, `evaluation_notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2025-01-01', 'completed', 95.00, 90.00, 1, '2025-01-16', '2027-01-16', 'Excellent performance', '2025-09-05 15:44:23', '2025-09-05 15:44:23'),
(2, 1, 2, '2025-01-02', 'completed', 88.00, 85.00, 1, '2025-01-16', '2027-01-16', 'Good participation', '2025-09-05 15:44:23', '2025-09-05 15:44:23'),
(3, 2, 3, '2025-02-01', 'completed', 92.00, 89.00, 1, '2025-02-08', '2026-02-08', 'Strong practical skills', '2025-09-05 15:44:23', '2025-09-05 15:44:23'),
(4, 2, 4, '2025-02-01', 'completed', 85.00, 80.00, 1, '2025-02-08', '2026-02-08', 'Needs more CPR practice', '2025-09-05 15:44:23', '2025-09-05 15:44:23'),
(5, 3, 5, '2025-02-20', 'registered', NULL, NULL, 0, NULL, NULL, NULL, '2025-09-05 15:44:23', '2025-09-05 15:44:23'),
(6, 3, 1, '2025-02-21', 'registered', NULL, NULL, 0, NULL, NULL, NULL, '2025-09-05 15:44:23', '2025-09-05 15:44:23'),
(7, 4, 2, '2025-03-10', 'registered', NULL, NULL, 0, NULL, NULL, NULL, '2025-09-05 15:44:23', '2025-09-05 15:44:23'),
(8, 5, 3, '2025-03-25', 'registered', NULL, NULL, 0, NULL, NULL, NULL, '2025-09-05 15:44:23', '2025-09-05 15:44:23'),
(9, 6, 4, '2025-04-10', 'registered', NULL, NULL, 0, NULL, NULL, NULL, '2025-09-05 15:44:23', '2025-09-05 15:44:23'),
(10, 7, 5, '2025-04-20', 'registered', NULL, NULL, 0, NULL, NULL, NULL, '2025-09-05 15:44:23', '2025-09-05 15:44:23'),
(11, 1, 10, '2025-09-05', 'registered', NULL, NULL, 0, NULL, NULL, NULL, '2025-09-05 15:50:28', '2025-09-05 15:50:28');

-- --------------------------------------------------------

--
-- Table structure for table `training_sessions`
--

CREATE TABLE `training_sessions` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `session_code` varchar(20) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `location` varchar(100) NOT NULL,
  `instructor` varchar(100) NOT NULL,
  `max_participants` int(11) NOT NULL,
  `status` enum('scheduled','ongoing','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `training_sessions`
--

INSERT INTO `training_sessions` (`id`, `course_id`, `session_code`, `title`, `description`, `start_date`, `end_date`, `start_time`, `end_time`, `location`, `instructor`, `max_participants`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'FSB-2025-01', 'Fire Safety Basics Batch 1', 'Basic fire safety course.', '2025-01-10', '2025-01-15', '09:00:00', '16:00:00', 'Training Center A', 'Capt. Santos', 30, 'ongoing', 1, '2025-09-05 15:42:18', '2025-09-05 17:00:24'),
(2, 2, 'FACPR-2025-01', 'First Aid & CPR Batch 1', 'Basic first aid training.', '2025-02-05', '2025-02-07', '08:00:00', '15:00:00', 'Training Center B', 'Nurse Dela Cruz', 25, 'completed', 1, '2025-09-05 15:42:18', '2025-09-05 15:42:18'),
(3, 3, 'DR-2025-01', 'Disaster Response Batch 1', 'Natural disaster response.', '2025-03-01', '2025-03-10', '09:00:00', '17:00:00', 'Training Center A', 'Chief Reyes', 40, 'scheduled', 1, '2025-09-05 15:42:18', '2025-09-05 15:42:18'),
(4, 7, 'HAZMAT-2025-01', 'Hazmat Handling Batch 1', 'Handling hazardous materials.', '2025-03-15', '2025-03-20', '08:00:00', '16:00:00', 'Training Center C', 'Engr. Mendoza', 20, 'completed', 1, '2025-09-05 15:42:18', '2025-09-05 18:07:30'),
(5, 5, 'RESCUE-2025-01', 'Rescue Ops Batch 1', 'Basic rescue operations.', '2025-04-01', '2025-04-10', '08:30:00', '16:30:00', 'Field Site Alpha', 'Lt. Garcia', 35, 'scheduled', 1, '2025-09-05 15:42:18', '2025-09-05 15:42:18'),
(6, 6, 'LEAD-2025-01', 'Leadership in Emergencies', 'Crisis leadership skills.', '2025-04-20', '2025-04-25', '09:00:00', '16:00:00', 'Training Center D', 'Maj. Cruz', 30, 'scheduled', 1, '2025-09-05 15:42:18', '2025-09-05 15:42:18'),
(7, 7, 'FFT-2025-01', 'Firefighting Tactics', 'Advanced firefighting techniques.', '2025-05-01', '2025-05-12', '07:30:00', '15:30:00', 'Training Ground', 'Capt. Villanueva', 40, 'scheduled', 1, '2025-09-05 15:42:18', '2025-09-05 15:42:18'),
(8, 8, 'ECOMM-2025-01', 'Emergency Comms', 'Radio and signal training.', '2025-05-15', '2025-05-17', '10:00:00', '14:00:00', 'Training Center E', 'Sgt. Bautista', 20, 'scheduled', 1, '2025-09-05 15:42:18', '2025-09-05 15:42:18'),
(9, 9, 'VEX-2025-01', 'Vehicle Extrication', 'Rescue techniques for car crashes.', '2025-06-01', '2025-06-07', '08:00:00', '17:00:00', 'Rescue Training Field', 'Capt. Ramos', 25, 'scheduled', 1, '2025-09-05 15:42:18', '2025-09-05 15:42:18'),
(10, 10, 'ICS-2025-01', 'Incident Command System', 'Managing large incidents.', '2025-06-15', '2025-06-20', '09:00:00', '17:00:00', 'HQ Auditorium', 'Col. Hernandez', 50, 'scheduled', 1, '2025-09-05 15:42:18', '2025-09-05 15:42:18'),
(11, 8, '22121885', 'Emergency Communcation batch 5', 'iloveyou', '2025-09-06', '2025-09-16', '13:00:00', '04:00:00', '57 gold extention barranggay commonwealth quezon city', 'Yukki Kyle', 50, 'scheduled', 1, '2025-09-05 16:19:56', '2025-09-05 16:19:56');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `certifications`
--
ALTER TABLE `certifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `certification_number` (`certification_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `training_assessments`
--
ALTER TABLE `training_assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `enrollment_id` (`enrollment_id`);

--
-- Indexes for table `training_courses`
--
ALTER TABLE `training_courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_code` (`course_code`),
  ADD KEY `category` (`category`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `training_enrollments`
--
ALTER TABLE `training_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `training_sessions`
--
ALTER TABLE `training_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_code` (`session_code`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `certifications`
--
ALTER TABLE `certifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `training_assessments`
--
ALTER TABLE `training_assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `training_courses`
--
ALTER TABLE `training_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `training_enrollments`
--
ALTER TABLE `training_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `training_sessions`
--
ALTER TABLE `training_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `certifications`
--
ALTER TABLE `certifications`
  ADD CONSTRAINT `certifications_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `training_assessments`
--
ALTER TABLE `training_assessments`
  ADD CONSTRAINT `training_assessments_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `training_enrollments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `training_enrollments`
--
ALTER TABLE `training_enrollments`
  ADD CONSTRAINT `training_enrollments_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `training_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `training_sessions`
--
ALTER TABLE `training_sessions`
  ADD CONSTRAINT `training_sessions_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
