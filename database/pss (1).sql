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
-- Database: `pss`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `shift_schedule_id` int(11) DEFAULT NULL,
  `check_in` datetime DEFAULT NULL,
  `check_out` datetime DEFAULT NULL,
  `hours_worked` decimal(4,2) DEFAULT NULL,
  `status` enum('present','absent','late','early_departure','on_leave') NOT NULL DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `employee_id`, `leave_type_id`, `start_date`, `end_date`, `reason`, `status`, `approved_by`, `approved_at`, `created_at`, `updated_at`) VALUES
(1, 4, 3, '2025-09-06', '2025-09-07', 'asdasd', 'approved', 1, '2025-09-05 19:08:13', '2025-09-05 10:55:58', '2025-09-05 11:08:13'),
(2, 5, 1, '2025-09-06', '2025-09-07', 'asd', 'rejected', 1, '2025-09-05 18:56:45', '2025-09-05 10:56:16', '2025-09-05 10:56:45'),
(3, 1, 3, '2025-09-06', '2025-09-18', 'asddfa', 'cancelled', NULL, NULL, '2025-09-05 11:08:33', '2025-09-05 11:08:38'),
(4, 4, 4, '2025-09-06', '2025-09-07', 'SAD', 'approved', 1, '2025-09-05 19:27:56', '2025-09-05 11:27:52', '2025-09-05 11:27:56');

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `max_days` int(11) NOT NULL DEFAULT 0,
  `requires_approval` tinyint(1) NOT NULL DEFAULT 1,
  `color` varchar(7) DEFAULT '#6c757d',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`id`, `name`, `description`, `max_days`, `requires_approval`, `color`, `created_at`, `updated_at`) VALUES
(1, 'Vacation Leave', 'Paid time off for vacation', 15, 1, '#28a745', '2025-08-31 09:39:21', '2025-08-31 09:39:21'),
(2, 'Sick Leave', 'Paid time off for illness', 10, 0, '#dc3545', '2025-08-31 09:39:21', '2025-08-31 09:39:21'),
(3, 'Emergency Leave', 'Unplanned time off for emergencies', 5, 0, '#ffc107', '2025-08-31 09:39:21', '2025-08-31 09:39:21'),
(4, 'Maternity/Paternity', 'Leave for new parents', 60, 1, '#17a2b8', '2025-08-31 09:39:21', '2025-08-31 09:39:21');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_type_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `notification_type_id`, `title`, `message`, `priority`, `is_read`, `read_at`, `created_at`, `updated_at`) VALUES
(1, 2, 5, 'qwe', 'qweqweqwsadasdasdasdasdasd', 'high', 0, NULL, '2025-09-05 11:37:33', '2025-09-05 11:37:33');

-- --------------------------------------------------------

--
-- Table structure for table `notification_types`
--

CREATE TABLE `notification_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'bx-bell',
  `color` varchar(7) DEFAULT '#6c757d',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_types`
--

INSERT INTO `notification_types` (`id`, `name`, `description`, `icon`, `color`, `created_at`, `updated_at`) VALUES
(1, 'Shift Change', 'Notifications about shift changes', 'bx-time', '#007bff', '2025-09-04 23:36:20', '2025-09-04 23:36:20'),
(2, 'Leave Request', 'Notifications about leave requests', 'bx-calendar-event', '#28a745', '2025-09-04 23:36:20', '2025-09-04 23:36:20'),
(3, 'Emergency Alert', 'Emergency notifications and alerts', 'bx-error', '#dc3545', '2025-09-04 23:36:20', '2025-09-04 23:36:20'),
(4, 'System Update', 'System maintenance and updates', 'bx-cog', '#6c757d', '2025-09-04 23:36:20', '2025-09-04 23:36:20'),
(5, 'General Message', 'General information messages', 'bx-message', '#17a2b8', '2025-09-04 23:36:20', '2025-09-04 23:36:20');

-- --------------------------------------------------------

--
-- Table structure for table `shift_schedules`
--

CREATE TABLE `shift_schedules` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `shift_type_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('scheduled','confirmed','cancelled','completed') NOT NULL DEFAULT 'scheduled',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shift_schedules`
--

INSERT INTO `shift_schedules` (`id`, `employee_id`, `shift_type_id`, `date`, `notes`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 4, 3, '2025-09-10', 'ily', 'scheduled', 1, '2025-09-05 07:36:20', '2025-09-05 07:36:20'),
(2, 1, 2, '2025-09-11', 'wow', 'scheduled', 1, '2025-09-05 07:43:20', '2025-09-05 07:43:20'),
(3, 2, 4, '2025-09-30', 'sadws', 'scheduled', 1, '2025-09-05 08:23:53', '2025-09-05 08:23:53'),
(5, 2, 1, '2025-09-14', '', 'scheduled', 1, '2025-09-05 08:24:41', '2025-09-05 08:24:41'),
(6, 4, 1, '2025-09-13', 'dfsfsdfsd', 'scheduled', 1, '2025-09-05 09:21:53', '2025-09-05 09:21:53'),
(7, 5, 3, '2025-09-15', 'dfsfsdfsd', 'scheduled', 1, '2025-09-05 09:22:46', '2025-09-05 09:22:46');

-- --------------------------------------------------------

--
-- Table structure for table `shift_swaps`
--

CREATE TABLE `shift_swaps` (
  `id` int(11) NOT NULL,
  `original_employee_id` int(11) NOT NULL,
  `requested_employee_id` int(11) NOT NULL,
  `shift_schedule_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shift_types`
--

CREATE TABLE `shift_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#007bff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shift_types`
--

INSERT INTO `shift_types` (`id`, `name`, `start_time`, `end_time`, `description`, `color`, `created_at`, `updated_at`) VALUES
(1, 'Day Shift', '08:00:00', '16:00:00', 'Standard day shift', '#007bff', '2025-08-31 09:39:21', '2025-08-31 09:39:21'),
(2, 'Night Shift', '16:00:00', '00:00:00', 'Standard night shift', '#6f42c1', '2025-08-31 09:39:21', '2025-08-31 09:39:21'),
(3, 'Graveyard Shift', '00:00:00', '08:00:00', 'Overnight shift', '#343a40', '2025-08-31 09:39:21', '2025-08-31 09:39:21'),
(4, 'Swing Shift', '12:00:00', '20:00:00', 'Afternoon to evening shift', '#fd7e14', '2025-08-31 09:39:21', '2025-08-31 09:39:21'),
(5, 'Evening Shift', '14:00:00', '22:00:00', 'Evening shift', '#007bff', '2025-09-05 08:53:38', '2025-09-05 08:53:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `shift_schedule_id` (`shift_schedule_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `leave_type_id` (`leave_type_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `notification_type_id` (`notification_type_id`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `notification_types`
--
ALTER TABLE `notification_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `shift_schedules`
--
ALTER TABLE `shift_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `shift_type_id` (`shift_type_id`),
  ADD KEY `date` (`date`);

--
-- Indexes for table `shift_swaps`
--
ALTER TABLE `shift_swaps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `original_employee_id` (`original_employee_id`),
  ADD KEY `requested_employee_id` (`requested_employee_id`),
  ADD KEY `shift_schedule_id` (`shift_schedule_id`);

--
-- Indexes for table `shift_types`
--
ALTER TABLE `shift_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notification_types`
--
ALTER TABLE `notification_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `shift_schedules`
--
ALTER TABLE `shift_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `shift_swaps`
--
ALTER TABLE `shift_swaps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shift_types`
--
ALTER TABLE `shift_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`shift_schedule_id`) REFERENCES `shift_schedules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `attendance_logs_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `frsm`.`employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `frsm`.`employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`notification_type_id`) REFERENCES `notification_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `frsm`.`users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shift_schedules`
--
ALTER TABLE `shift_schedules`
  ADD CONSTRAINT `shift_schedules_ibfk_1` FOREIGN KEY (`shift_type_id`) REFERENCES `shift_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shift_schedules_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `frsm`.`employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shift_swaps`
--
ALTER TABLE `shift_swaps`
  ADD CONSTRAINT `shift_swaps_ibfk_1` FOREIGN KEY (`shift_schedule_id`) REFERENCES `shift_schedules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shift_swaps_ibfk_3` FOREIGN KEY (`original_employee_id`) REFERENCES `frsm`.`employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shift_swaps_ibfk_4` FOREIGN KEY (`requested_employee_id`) REFERENCES `frsm`.`employees` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
